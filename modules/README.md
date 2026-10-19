# Modules

> A module is a self-contained subsystem that ships with the application and carries its own wiring - services,
> entities, routes, templates, translations, migrations and tests - built exactly like a plugin, plus a
> **machine-enforced perimeter**: only its `Contract/` namespace may be imported from outside, and Mago Guard fails
> the build when anything reaches past it. A plugin's boundary is a convention; a module's is a rule.

Modules are always on. There is no enable flag and no way to switch one off - optionality is what `plugins/` is
for. `trust/` is the smallest complete module; read its `config/` and `mago.toml` alongside this file.

## A directory that exists is a module that loads

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

`App\Kernel::getModuleConfigDirs()` globs `modules/*/config` and feeds it to `configureContainer()` and
`configureRoutes()`, so there is no registration list to keep in sync. Modules ship no bundles, so
`registerBundles()` ignores them.

**`packages/cache.yaml` declares only the `pools` key it adds.** Symfony merges prototyped config across imports, so
the module's pool joins the list core already defines; redeclaring `app` or `default_redis_provider` fights core's
file instead of extending it.

## A module's tables carry its name

A module's name is one lowercase word - no dash, no underscore - used once across core and plugin modules, and every
table its entities map is `mod_<name>` or starts with `mod_<name>_`. No entity outside a module may map a table
starting with `mod_`. So a table name says which module owns it, and no plugin can quietly write into a module's
namespace. `tests/Unit/ModuleTableTest.php` enforces all three and names the offending entity.

The prefix is derived from the module's directory name, not configured, so there is nothing to keep in sync. A
multi-word name is shortened by renaming the module directory, never by cutting the prefix.

## The contract crosses the line, nothing else does

Everything private lives under `Internal/`, which keeps the guard rule simple: the restriction targets
`Module\<Name>\Internal\**`, and `Contract/` is public by construction rather than by rule precedence.

The contract speaks in **scalars, enums and readonly value objects only**. No entity, no repository and no Doctrine
type crosses the boundary - a user is an `int $userId`, never an object - because an entity handed across the line
drags the whole ORM graph with it, and the perimeter stops meaning anything.

The consequence a consumer hits first: **a module's data can never participate in a caller's SQL.** "Sort members
by score" is a fetch-the-map-and-sort-in-PHP operation, not a JOIN. For a small derived dataset that costs nothing;
for a query-heavy subsystem it is the reason not to make it a module at all.

## Mago Guard holds the perimeter in both directions

The rules run as `just checkMagoGuard`, in the `just check` chain and as their own CI step, and live in three places:

- **`modules/<name>/mago.toml`** - the module's own entries: its inbound restriction, its outbound permit list, the
  rules for its `Tests\` and migrations namespaces, and its analyzer exclusion. `modules/trust/mago.toml` is the
  reference.
- **`tests/config/mago.toml`** - core's config. It lists every module file in `extends` and holds the catch-all rules
  the allowlist mode needs.
- **`tests/config/mago-rules.toml`** - the shared rule file every plugin's config extends: the generic inbound
  backstops and the structural rules on `Contract/`.

A module file is a fragment, never a run of its own. An inbound violation is reported on the file doing the reaching,
which is outside the module, and one outbound rule puts the whole run into allowlist mode, so every module's entries
must sit in the same run as core. `extends` appends list entries from each file rather than replacing them, but takes
no globs - hence the explicit list.

Three kinds of rule, all needed:

- **Inbound** - a `[[guard.perimeter.restrictions]]` entry: nothing outside the module may depend on
  `Module\<Name>\Internal\**`.
- **Outbound** - a `[[guard.perimeter.rules]]` entry whose `permit` list is everything the module itself may reach.
  A module nobody can reach into, but which reaches freely into the rest of the application, is not isolated - it is
  just inconveniently located.
- **Shape** - `[[guard.structural.rules]]` on `Module\*\Contract\**`: interfaces, enums and final readonly classes
  only.

Three details of the tool are easy to get wrong:

- **`namespace` must be `@global` or end with a backslash.** Anything else is a config parse error.
- **`[[guard.perimeter.rules]]` is global allowlist mode.** The moment one rule exists, a namespace with no matching
  rule has *every* dependency reported as `No matching architectural rule found`. That is why core's
  `tests/config/mago.toml` carries `**` catch-all rules for `@global`, `App\`, `Plugin\` and `Tests\`: they state that
  everything outside the module trees is not perimeter-guarded. The most specific matching rule wins, which is what
  lets a module rule bite.
- **`@global` in a `permit` list covers PHP's own functions and classes** - `DateTimeImmutable`, `Override`,
  `count()`. Without it a module cannot call `sprintf`.

Every entry on a permit list carries an inline comment saying why; a bare FQCN tells the next reader nothing about
whether it was considered or merely convenient.

### The inbound rule comes in two layers

A generic `dependency = "Module\*\Internal\**"` backstop holds the boundary against core, plugins and tests for every
module, including one whose own entry was never added; the per-module entry narrows it to the owning module. The two
`*` in the backstop do **not** correlate: `allow-from = ["Module\*\**"]` reads as "some module may reach some
module's internals", not "the owning module", so on its own it lets one module import another's `Internal` class
without a report. Restrictions AND together, so stacking the precise entry on the backstop keeps the precise rule
binding, and adding the backstop loosens nothing.

The outbound half cannot be collapsed into a wildcard at all. A `permit` list is the per-module decision: a
`Module\*\` catch-all would hand every future module the exceptions one module was granted.

The backstops - `Module\*\Internal\**` and `Plugin\*\Module\*\Internal\**` - and the two structural rules for each
`Contract\**` root live in `tests/config/mago-rules.toml`. `bin/mago.sh` skips guard for a config that declares no
guard rules at all, so keeping them in the shared file is what makes every plugin run enforce them: a plugin cannot
import a module's `Internal` namespace either.

`tests/Unit/ModulePerimeterTest.php` globs `modules/*/src` and `plugins/*/modules/*/src` and fails, with the exact
line to paste, when a module has no `mago.toml`, when that file is missing from the `extends` of the run that owns it
(core's, or its plugin's), when it lacks its inbound restriction, its outbound rule or a rule for its test suite, or
when that test rule permits a catch-all. It also checks that the backstops and both structural rules are present in
the shared file. A new module therefore cannot half-exist.

**Prove a new rule fails before you trust it.** Write a file that violates it, watch `just checkMagoGuard` go red,
then delete the file. A mis-scoped rule that silently matches nothing looks exactly like a rule that passes.

## A module tests itself

A module proves its own behaviour, in this repository, on every commit. Its functional tests drive it the way a
consumer would - through `Contract/` - with stand-in consumers it ships in `modules/<name>/tests/Stub/`, on a
database that holds nothing but the schema and the install seed. `just testModules` runs them, on three pieces of
infrastructure in `tests/`:

- **`tests/Module/ModuleKernel.php`** boots core and every module and no plugin, still in the `test` environment,
  with its own cache directory and its own database. It loads each `modules/<name>/tests/config/services.yaml`, so
  stubs exist only in this kernel. `tests/config/phpunit.modules.xml` points the suite at it.
- **`tests/bin/module-db.sh`** builds that database when `test-stamp --config test-stamp-modules` reports that
  something shaping it changed (an entity, a mapping, the seed), or when it is missing.
- **`tests/Module/Members.php`** persists the members a test needs. Each test creates its own rows, and the DAMA
  extension rolls them back.

Four rules keep these tests honest:

- **A stub claims only what it created.** Core's own consumers are loaded too - its email types, its identity
  provider, its comment targets - so a stub answers for its own identifiers (`stub_item`, `stub-context`,
  `module_test_triggered`) and stays silent for everything else.
- **A stub whose state a test sets is a plain mutable service.** The kernel reboots between tests, so that state
  never leaks into the next one.
- **A test may reach its module's `Internal/`, not the rest of core.** The `Tests\` rule in the module's `mago.toml`
  permits what the module's code may, plus PHPUnit and `Tests\Module\**` - the shared harness, because a `User` row
  needs core enums no module is permitted. It does not permit `App\**` or `Tests\**`: a test that could import any
  core class would drift back into testing the module through its real consumers. Any further core class is listed
  there with its reason - circulation's tests list the four classes behind `CommentService`, because that service is
  `final` and cannot be stubbed - and `ModulePerimeterTest` rejects a catch-all.
- **Stubs stay out of `config/services_test.yaml`.** That file loads into every test container, so a stub there
  would leak into other suites - a stub email type would appear in the email admin's planned list and in the preview
  sweep's counts. Stubs live in `modules/<name>/tests/config/services.yaml`, which only the module kernel imports.

A module's unit tests mirror `src/`: `modules/<name>/tests/Unit/Contract/` and `.../Unit/Internal/`, one file per
class, named after it. Tests that need real consumers on real data - a plugin driving a module, say - belong to the
application's functional suite, not to the module.

## Adding a module

1. `modules/<name>/` with the directory shape above, `<name>` one lowercase word. Namespace root `Module\<Name>\`,
   tables `mod_<name>_*`.
2. The config files, copied from `modules/trust/config/` with the paths swapped.
3. `modules/<name>/mago.toml`, copied from `modules/trust/mago.toml` with the names swapped: the perimeter
   restriction, the outbound rule, a rule for its `Tests\` and its migrations namespace, and the analyzer exclusion
   for its tests. Add the file to the `extends` list in `tests/config/mago.toml`; the module already matches the
   `modules/*` source globs there. Run `just testUnit tests/Unit/ModulePerimeterTest.php` and let it name anything
   missing.
4. Prove all three rule kinds fail on a deliberate violation.
5. `modules/<name>/tests/Stub/` with a stand-in consumer for each seam, wired by
   `modules/<name>/tests/config/services.yaml`, and `modules/<name>/tests/Functional/` driving the module through its
   contract - copy the shape from `modules/trust/tests/`.
6. A `README.md` in the module saying what it does and how to consume it, in the shape the other module READMEs
   share.

Nothing else needs touching - not `composer.json`, not `phpunit.xml`, not the Kernel.

## A plugin can ship modules of its own

A plugin carries modules in `plugins/<plugin>/modules/<name>/`, built exactly as above. What changes:

|                       | Core module              | Plugin module                                                                                 |
|-----------------------|--------------------------|-----------------------------------------------------------------------------------------------|
| Namespace root        | `Module\<Name>\`         | `Plugin\<Plugin>\Module\<Name>\`                                                              |
| Loads                 | always                   | with its plugin: container whenever the plugin's config loads, routes only when it is enabled |
| `mago.toml` listed in | `tests/config/mago.toml` | the plugin's `tests/config/mago.toml`                                                         |
| Guard rules name      | `Module\<Name>\...`      | `Plugin\<Plugin>\Module\<Name>\...`                                                           |

`plugins/autoload.php` registers the namespaces and `App\Kernel::getPluginModuleConfigDirs()` finds the config
directories. The module test kernel loads every plugin module in the checkout, still without any plugin, and imports
each one's stub wiring, so a plugin module tests itself under `just testModules` like any other. Its unit tests join
the unit suite.

Declaring a plugin module's outbound rule puts that plugin's whole run into allowlist mode, so the plugin's config
carries the same catch-alls core's does (`@global`, `App\`, `Plugin\`, `Tests\`, its migration namespace). A plugin
kept in its own repository adds its directory to `SCAN_ROOTS` in the gitignored
`config/tools/test-stamp-modules.local`, or changes to its entities never trigger a rebuild of the module test
database.
