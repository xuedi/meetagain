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
    config/
      services.yaml          the module's service definitions
      services_test.yaml     test-environment-only services (optional)
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
    tests/Unit/
    tests/Functional/
```

`App\Kernel::getModuleConfigDirs()` globs `modules/*/config` and feeds it to `configureContainer()`
and `configureRoutes()`. A directory that exists is a module that loads; there is no registration list
to keep in sync. Modules ship no bundles, so `registerBundles()` ignores them.

Two notes on the config files, both learned the hard way:

- **`packages/cache.yaml` must declare only the `pools` key it adds.** Symfony merges prototyped
  config across imports, so the module's pool joins the list core already defines. Redeclaring `app`
  or `default_redis_provider` fights core's file instead of extending it.
- **`services_test.yaml` is picked up for free.** The Kernel imports `{services}_{env}.yaml` from
  every config directory it loads, so a module can register test-only services - a stub consumer, for
  instance - without any test-kernel machinery.

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

The rules live in `tests/config/mago.toml` and run as `just checkMagoGuard`, in the `just check` chain
and as its own CI step. Three kinds of rule, all three needed:

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
    "App\\Admin\\**",
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
  That is why `tests/config/mago.toml` carries `**` catch-all rules for `@global`, `App\`, `Plugin\`
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

## Adding a module

1. `modules/<name>/` with the directory shape above. Namespace root `Module\<Name>\`.
2. The config files, copied from `modules/trust/config/` with the paths swapped.
3. `tests/config/mago.toml`: the module already matches the `modules/*` source globs; add its
   perimeter restriction, its outbound rule, and a rule for its `Tests\` namespace.
   `tests/Unit/ModulePerimeterTest.php` fails with the exact line to paste if any of the three is
   missing, so run `just testUnit tests/Unit/ModulePerimeterTest.php` and let it tell you.
4. Prove all three rule kinds fail on a deliberate violation.
5. A `README.md` in the module saying what it does and how to consume it.

Nothing else needs touching - not `composer.json`, not `phpunit.xml`, not the Kernel. Those were wired
once for the tree.
