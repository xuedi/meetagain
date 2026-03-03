# Plugin Development

MeetAgain's plugin system lets you extend the platform without touching core code.
Plugins integrate through well-defined hooks and interfaces — navigation, content tiles,
event filters, authorization, notifications, and more.

---

## What can plugins do?

| Hook / Interface | Purpose | Example plugin |
|---|---|---|
| `getMenuLinks()` | Add links to the main navigation | dishes, filmclub |
| `getEventTile()` | Render a custom box on event detail pages | filmclub (voting tile) |
| `getEventListItemTags()` | Add badges to events in list views | dishes (vegetarian tag) |
| `getMemberPageTop()` | Inject content above the admin member list | multisite |
| `getFooterAbout()` | Add content to the footer's About section | multisite |
| `preFixtures()` / `postFixtures()` | Run setup tasks around fixture loading | filmclub, multisite |
| `loadPostExtendFixtures()` | Create data tied to recurring event instances | filmclub |
| `runCronTasks()` | Schedule periodic background tasks | filmclub |
| `AdminNavigationInterface` | Add sections and links to the admin sidebar | filmclub, multisite |
| `EventFilterInterface` | Filter which events are visible | multisite |
| `MenuFilterInterface` | Filter navigation links by context | multisite |
| `CmsFilterInterface` | Filter which CMS pages are visible | multisite |
| `MemberFilterInterface` | Filter which members appear in lists | multisite |
| `ActionAuthorizationInterface` | Allow or deny user actions (RSVP, comments…) | multisite |
| `ActionAuthorizationMessageProviderInterface` | Custom error messages for denied actions | multisite |
| `EventFilterFormContributorInterface` | Add fields to the event filter form | (custom) |
| `NotificationProviderInterface` | Provide notification counts to the bell icon | multisite |
| `EntityActionInterface` | React to core entity lifecycle events | multisite |

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

| Level | Example | Interfaces used |
|---|---|---|
| Simple | `dishes` | `Plugin` only — one menu link, no tiles |
| Intermediate | `filmclub` | `Plugin` + `AdminNavigationInterface` — event tiles, cron, fixtures |
| Advanced | `multisite` | `Plugin` + 6 optional interfaces — multi-tenant filtering, authorization |

---

## Quick links

| Page | What you'll find |
|---|---|
| [Quick Start](quick-start.md) | Skeleton, minimal Kernel.php, enable commands |
| [Required Hooks](required-hooks.md) | Full reference for every `Plugin` interface method |
| [Optional Hooks](optional-hooks.md) | All optional interfaces with examples |
| [Architecture](architecture.md) | Namespaces, services, routes, templates, DB conventions |
| [Best Practices](best-practices.md) | Coding patterns that keep plugins safe and removable |
| [Data Fixtures](fixtures.md) | Cross-fixture references, fixture groups, hook timing |
| [Testing](testing.md) | Unit and functional testing guide for plugin code |
| [Troubleshooting](troubleshooting.md) | Common problems and solutions |
