# Modules

A module is a self-contained subsystem that ships as part of the application and carries its own
wiring: services, entities, repositories, routes, templates, translations, migrations and tests. In
that much it is built exactly like a plugin. The difference is a **machine-enforced perimeter**: only
a module's `Contract/` namespace may be imported from outside it, and Mago Guard fails the build when
anything reaches past it. A plugin's boundary is a convention; a module's is a rule.

Modules are always on. There is no enable flag and no way to switch one off - optionality is what
`plugins/` is for.

`trust/` is the first module and the worked example. Read its `config/` alongside this file.

## Anatomy

```
modules/
  autoload.php               registers Module\<Name>\ and Module\<Name>\Tests\ as PSR-4 roots
  <name>/
    README.md                what this module does
    mago.toml                the module's guard perimeter, pulled into core's Mago run
    config/
      services.yaml          the module's service definitions
      routes.yaml            attribute routes over src/Internal/Controller
      packages/doctrine.yaml ORM mapping + migrations namespace
      packages/twig.yaml     the template namespace
      packages/translation.yaml
      packages/cache.yaml    any cache pools the module owns (optional)
    migrations/
    src/Contract/            the public surface: interfaces, enums, readonly value objects
    src/Internal/            everything else - unreachable from outside
    templates/
    translations/
    tests/
      Unit/Contract/         unit tests, mirroring src/ one class per file
      Unit/Internal/
      Functional/            the module driven through its contract by stub consumers
      Stub/                  those stub consumers, shared with the unit tests
      config/services.yaml   wires the stubs; loaded only by the module test kernel
```

`App\Kernel::getModuleConfigDirs()` globs `modules/*/config` and feeds it to `configureContainer()`
and `configureRoutes()`. A directory that exists is a module that loads; there is no registration list
to keep in sync. Modules ship no bundles, so `registerBundles()` ignores them.

One note on the config files: **`packages/cache.yaml` must declare only the `pools` key it adds.**
Symfony merges prototyped config across imports, so the module's pool joins the list core already
defines. Redeclaring `app` or `default_redis_provider` fights core's file instead of extending it.

## `Contract/` and `Internal/`

Everything private lives under `Internal/`. That is what makes the guard rule simple: the restriction
targets `Module\<Name>\Internal\**`, and `Contract/` is public by construction rather than by a
rule-precedence argument.

The contract speaks in **scalars, enums and readonly value objects only**. No entity, no repository
and no Doctrine type ever crosses the boundary - a user is an `int $userId`, never an object. This is
not fussiness: an entity handed across the line drags the whole ORM graph with it, and the perimeter
stops meaning anything.

One consequence is worth naming up front, because it is the first thing a consumer hits: **a module's
data can never participate in a caller's SQL.** "Sort members by score" is a fetch-the-map-and-sort-in-PHP
operation, not a JOIN. For a small derived dataset that costs nothing. For a query-heavy subsystem it
is the reason not to make it a module at all.

## How Mago Guard enforces it

The rules run as `just checkMagoGuard`, in the `just check` chain and as its own CI step, and live in
three places:

- **`modules/<name>/mago.toml`** - the module's own entries: its inbound restriction, its outbound
  permit list, the rules for its `Tests\` and migrations namespaces, and its analyzer exclusion.
- **`tests/config/mago.toml`** - core's config. It lists every module file in `extends`, and holds what
  all modules share: the catch-all rules and the structural rules on `Contract/`.
- **`tests/config/mago-rules.toml`** - the generic inbound backstop, in the shared rule file every
  plugin's config extends, so each plugin run enforces it too - with `--perimeter`, since plugins
  declare no structural rules.

A module file is a fragment, never a run of its own. An inbound violation is reported on the file
doing the reaching, which is outside the module, and one outbound rule puts the whole run into
allowlist mode, so every module's entries must sit in the same run as core. `extends` appends list
entries from each file rather than replacing them, but it takes no globs - hence the explicit list.

Three kinds of rule, all three needed:

```toml
# Inbound: nothing outside the module may reach past its Contract namespace.
# The generic backstop covers every module; the specific entry narrows it to the owner.
[[guard.perimeter.restrictions]]
dependency = "Module\\*\\Internal\\**"
allow-from = ["Module\\*\\**"]

[[guard.perimeter.restrictions]]
dependency = "Module\\Trust\\Internal\\**"
allow-from = ["Module\\Trust\\**"]

# Outbound: what the module itself is allowed to reach.
[[guard.perimeter.rules]]
namespace = "Module\\Trust\\"
permit = [
    "Module\\Trust\\**",
    "@global",
    "App\\Entity\\User",
    "App\\Controller\\AbstractController",
    "Doctrine\\**", "Symfony\\**", "Twig\\**", "Psr\\**",
]

# Shape: the contract carries interfaces, enums and readonly value objects, nothing else.
[[guard.structural.rules]]
on = "Module\\*\\Contract\\**"
must-be = ["interface", "enum", "class"]

[[guard.structural.rules]]
on = "Module\\*\\Contract\\**"
target = "class"
must-be-final = true
must-be-readonly = true
```

Both directions matter. A module nobody can reach into, but which itself reaches freely into the rest
of the application, is not isolated - it is just inconveniently located.

Three details of the tool that are easy to get wrong:

- **`namespace` must be `@global` or end with a backslash.** Anything else is a config parse error.
- **`[[guard.perimeter.rules]]` is global allowlist mode.** The moment one rule exists, a namespace
  with no matching rule has *every* dependency reported as `No matching architectural rule found`.
  That is why core's `tests/config/mago.toml` carries `**` catch-all rules for `@global`, `App\`, `Plugin\`
  and `Tests\`: they state the current reality, that everything outside `modules/` is not
  perimeter-guarded yet. The most specific matching rule wins, which is what lets the module rule bite.
- **`@global` in a `permit` list covers PHP's own functions and classes** - `DateTimeImmutable`,
  `Override`, `count()`. Without it a module cannot call `sprintf`.

Every entry on a permit list is a decision. Keep the inline comment saying why; a bare FQCN tells the
next reader nothing about whether it was considered or merely convenient.

The inbound rule comes in two layers, and both are needed. A generic
`dependency = "Module\*\Internal\**"` backstop holds the boundary against core, plugins and tests for
every module; the per-module entry narrows it to the owning module. The wildcards in the backstop do
**not** correlate - on its own it would let one module reach into another's internals - but restrictions
AND together, so stacking them keeps the precise rule binding.

**Prove a new rule fails before you trust it.** Write a file that violates it, watch
`just checkMagoGuard` go red, then delete the file. A guard nobody has seen fail is a guard nobody
should trust.

## A module tests itself

A module proves its own behaviour, in this repository, on every commit. Its functional tests drive it the
way a consumer would - through `Contract/` - with stand-in consumers it ships in `modules/<name>/tests/Stub/`, on a
database that holds nothing but the schema and the install seed.

`just testModules` runs them. Three pieces of test infrastructure in `tests/` make that possible:

- **`tests/Module/ModuleKernel.php`** boots core and every module and no plugin, still in the `test`
  environment, with its own cache directory and its own database (`meetAgain_test_modules`). It loads each
  `modules/<name>/tests/config/services.yaml`, so stubs exist only in this kernel and never in any other
  test container. `tests/config/phpunit.modules.xml` points the suite at it.
- **`tests/bin/module-db.sh`** builds that database when `test-stamp --config test-stamp-modules` reports
  that something shaping it changed (an entity, a mapping, the seed), or when it is missing. A run with
  nothing changed skips the build entirely.
- **`tests/Module/Members.php`** persists the members a test needs. Each test creates its own rows, and the
  DAMA extension rolls them back.

Three rules keep these tests honest:

- **A stub claims only what it created.** Core's own consumers are loaded too - its email types, its
  identity provider, its comment targets - so a stub answers for its own identifiers (`stub_item`,
  `stub-context`, `module_test_triggered`) and stays silent for everything else.
- **A stub whose state a test sets is a plain mutable service.** The kernel reboots between tests, so that
  state never leaks into the next one.
- **A test may reach its module's `Internal/`, not the rest of core.** The `Tests\` rule in the module's
  `mago.toml` permits what the module's code may, plus PHPUnit and `Tests\Module\**`. Any further core class
  is listed there with its reason, and `ModulePerimeterTest` rejects a catch-all.

A module's unit tests mirror `src/`: `modules/<name>/tests/Unit/Contract/` and `.../Unit/Internal/`, one file per
class, named after it. Tests that need real consumers on real data - a plugin driving a module, say -
belong to the application's functional suite, not to the module.

## Adding a module

1. `modules/<name>/` with the directory shape above. Namespace root `Module\<Name>\`.
2. The config files, copied from `modules/trust/config/` with the paths swapped.
3. `modules/<name>/mago.toml`, copied from `modules/trust/mago.toml` with the names swapped: the
   perimeter restriction, the outbound rule, a rule for its `Tests\` and its migrations namespace,
   and the analyzer exclusion for its tests. Add the file to the `extends` list in
   `tests/config/mago.toml`; the module already matches the `modules/*` source globs there.
   `tests/Unit/ModulePerimeterTest.php` fails with the exact line to paste if the file, its `extends`
   entry or one of the three rules is missing, so run `just testUnit tests/Unit/ModulePerimeterTest.php`
   and let it tell you.
4. Prove all three rule kinds fail on a deliberate violation.
5. `modules/<name>/tests/Stub/` with a stand-in consumer for each seam, wired by
   `modules/<name>/tests/config/services.yaml`, and `modules/<name>/tests/Functional/` driving the module through its contract - copy the shape from `modules/trust/tests/`.
6. A `README.md` in the module saying what it does and how to consume it.

Nothing else needs touching - not `composer.json`, not `phpunit.xml`, not the Kernel. Those were wired
once for the tree.
