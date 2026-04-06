# Plugin Development

MeetAgain's plugin system lets you extend the platform without touching core code.
Plugins integrate through well-defined hooks and interfaces — navigation, content tiles,
event filters, authorization, notifications, and more.

---

## What can plugins do?

| Hook / Interface                              | Purpose                                              | Example plugin          |
|-----------------------------------------------|------------------------------------------------------|-------------------------|
| `getMenuLinks()`                              | Add links to the main navigation                     | dishes, filmclub        |
| `getEventTile()`                              | Render a custom box on event detail pages            | filmclub (voting tile)  |
| `getEventListItemTags()`                      | Add badges to events in list views                   | dishes (vegetarian tag) |
| `getMemberPageTop()`                          | Inject content above the admin member list           | filmclub                |
| `getFooterAbout()`                            | Add content to the footer's About section            | filmclub                |
| `preFixtures()` / `postFixtures()`            | Run setup tasks around fixture loading               | filmclub                |
| `loadPostExtendFixtures()`                    | Create data tied to recurring event instances        | filmclub                |
| `runCronTasks()`                              | Schedule periodic background tasks                   | filmclub                |
| `AdminNavigationInterface`                    | Add sections and links to the admin sidebar          | filmclub, bookclub      |
| `EventFilterInterface`                        | Filter which events are visible                      | (custom)                |
| `MenuFilterInterface`                         | Filter navigation links by context                   | (custom)                |
| `CmsFilterInterface`                          | Filter which CMS pages are visible                   | (custom)                |
| `MemberFilterInterface`                       | Filter which members appear in lists                 | (custom)                |
| `PermissionVoterInterface`                    | Override or veto any permission decision             | multisite               |
| `EventActionGuardInterface`                   | Allow or deny event-scoped actions (RSVP, comments…) | (custom)                |
| `EventActionDenyMessageProviderInterface`     | Custom error messages for denied event actions       | (custom)                |
| `EventFilterFormContributorInterface`         | Add fields to the event filter form                  | (custom)                |
| `NotificationProviderInterface`               | Provide notification counts to the bell icon         | dinnerclub              |
| `EntityActionInterface`                       | React to core entity lifecycle events                | filmclub                |

---

## Developer journey

1. **Create the skeleton** — directory structure, `Kernel.php`, config files
2. **Implement `Kernel.php`** — at minimum, fill every method in the `Plugin` interface
3. **Choose optional interfaces** — add only the hooks your plugin needs
4. **Add fixtures** — extend `AbstractFixture` for type-safe test data
5. **Test** — unit-test services in isolation, functional-test key flows
6. **Enable** — run `just plugin-enable your-plugin` and `just devModeFixtures`

---

## Complexity levels

| Level        | Example     | Interfaces used                                                          |
|--------------|-------------|--------------------------------------------------------------------------|
| Simple       | `dishes`    | `Plugin` only — one menu link, no tiles                                  |
| Intermediate | `filmclub`  | `Plugin` + `AdminNavigationInterface` — event tiles, cron, fixtures      |
| Advanced     | `multisite` | `Plugin` + 6 optional interfaces — multi-tenant filtering, authorization |

---

## Quick links

| Page                                             | What you'll find                                              |
|--------------------------------------------------|---------------------------------------------------------------|
| [Quick Start](quick-start.md)                    | Skeleton, minimal Kernel.php, enable commands                 |
| [Required Hooks](required-hooks.md)              | Full reference for every `Plugin` interface method            |
| [Optional Hooks](optional-hooks.md)              | All optional interfaces with examples                         |
| [Permissions](permissions.md)                    | Defining actions, default rules, custom voters, and templates |
| [Architecture](architecture.md)                  | Namespaces, services, routes, templates, DB conventions       |
| [Best Practices](best-practices.md)              | Coding patterns that keep plugins safe and removable          |
| [Data Fixtures](../core-development/fixtures.md) | Cross-fixture references, fixture groups, hook timing         |
| [Testing](testing.md)                            | Unit and functional testing guide for plugin code             |
| [Troubleshooting](troubleshooting.md)            | Common problems and solutions                                 |
