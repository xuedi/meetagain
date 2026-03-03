# Troubleshooting

Examples from existing plugins, plus solutions to common problems.

---

## Examples from existing plugins

Before diving into problems, these three plugins serve as reference implementations
at different complexity levels:

### Simple — Dishes

- Adds one menu link
- No event tiles, no filters
- Minimal `Kernel.php` with all methods returning empty/null
- **See:** `plugins/dishes/src/Kernel.php`

### Intermediate — Film Club

- Multiple menu links with priorities
- Event tiles (voting box on event detail page)
- `loadPostExtendFixtures` to create votes for recurring events
- Cron tasks to close expired votes
- `AdminNavigationInterface` for admin sidebar
- **See:** `plugins/filmclub/src/Kernel.php`

### Advanced — MultiSite

- `EventFilterInterface` — group-based event visibility
- `MenuFilterInterface` — domain-context menu filtering
- `ActionAuthorizationInterface` — membership-gated RSVP
- `AdminNavigationInterface` — multi-section admin sidebar
- `EntityActionInterface` — reacts to member and event lifecycle events
- **See:** `plugins/multisite/src/` — the reference for all plugin integration points

---

## Common problems

### Plugin not showing up

1. Check `config/plugins.php` — is the plugin key listed?
2. Run `just plugin-enable your-plugin` to add it.
3. Clear the cache: `just app cache:clear`

---

### Services not autowired

1. Check the namespace: must be `Plugin\YourPlugin\*`
2. Verify `config/services.yaml` has the correct `resource` path (`../src/`)
3. Ensure the class is **not** in the `exclude` list
4. Clear the container: `just app cache:clear`

---

### Templates not found

1. Use the Twig namespace: `@YourPlugin/template.html.twig`
2. Verify the template is in `plugins/your-plugin/templates/`
3. Check the subdirectory path matches: `@YourPlugin/sub/dir/file.html.twig`
4. Clear the template cache: `just app cache:clear`

---

### Fixtures not loading

1. Ensure fixture classes extend `App\DataFixtures\AbstractFixture`
2. Check the class is in `plugins/your-plugin/src/DataFixtures/`
3. Re-run: `just devModeFixtures`

---

### Filter interface not applied

**Symptom:** Events / menu links / members are not filtered even though your filter class exists.

**Cause:** Missing `#[AutoconfigureTag]` attribute.

**Fix:** Add the correct tag to your class:

```php
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.event_filter')]   // For EventFilterInterface
#[AutoconfigureTag('app.menu_filter')]    // For MenuFilterInterface
#[AutoconfigureTag('app.cms_filter')]     // For CmsFilterInterface
#[AutoconfigureTag('app.member_filter')]  // For MemberFilterInterface
readonly class YourFilter implements EventFilterInterface
{
    // ...
}
```

Then clear cache: `just app cache:clear`

---

### EntityAction handler not firing

**Symptom:** Your `EntityActionInterface` implementation is never called.

**Cause:** Missing `#[AutoconfigureTag('app.entity_action')]`.

**Fix:**

```php
#[AutoconfigureTag('app.entity_action')]
readonly class YourHandler implements EntityActionInterface
{
    // ...
}
```

---

### Admin section not appearing

1. Check that your controller implements `AdminNavigationInterface`
2. Verify `getAdminNavigationConfig()` returns a non-null `AdminNavigationConfig`
3. If `sectionRole` is set, confirm the current user has that role
4. Individual `AdminLink` entries with a `role` will be hidden if the user lacks that role

---

### Migrations not running

1. Migration files must be in `plugins/your-plugin/migrations/`
2. The plugin's `config/packages/doctrine_migrations.yaml` must point to that directory
3. Run: `just app doctrine:migrations:migrate`
4. For dev reset: `just devModeFixtures` runs migrations automatically

---

### Cache issues after config changes

After changing service configuration, routes, or Twig templates:

```bash
just app cache:clear
```

After enabling or disabling a plugin:

```bash
just plugin-enable your-plugin   # or plugin-disable
just app cache:clear
```
