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
- Export and import sections (`src/Portability/`)
- `AdminNavigationInterface` for admin sidebar
- **See:** `plugins/films/src/Kernel.php`

### Advanced — MultiSite

- `EventFilterInterface` — group-based event visibility
- `EventActionGuardInterface` — membership-gated RSVP
- `AdminNavigationInterface` — multi-section admin sidebar
- `EntityActionInterface` — reacts to member and event lifecycle events
- **See:** `plugins/multisite/src/` — the reference for all plugin integration points

---

## Common problems

### Plugin not showing up

Every plugin folder under `plugins/` is installed; an admin switches each one on or off.

1. Check its state: `just plugin-list` (runs `app:plugin:list`).
2. Switch it on at `/admin/plugin`, or run `just plugin-enable your-plugin` (`app:plugin:toggle on your-plugin`).
   A plugin whose `"requires"` lists another plugin stays off until that one is on.
3. A plugin folder that is new to the instance needs the container rebuilt: `just app cache:clear`

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

### Your data is missing after an import

**Symptom:** The import summary counts your plugin's rows as skipped, or your items never arrive.

1. The plugin must be switched on on the importing instance - a section of a switched-off plugin is skipped.
2. `getPluginKey()` on your contributor or section must return the plugin key - its folder name under `plugins/`.
3. Every exported item row needs a `ref` equal to the source item id, and `importItems()` must flush
   before returning - see [Optional Hooks](optional-hooks.md#export-and-import).

---

### Filter interface not applied

**Symptom:** Events / menu links / members are not filtered even though your filter class exists.

**Cause:** Missing `#[AutoconfigureTag]` attribute.

**Fix:** Add the correct tag to your class:

```php
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.event_filter')]   // For EventFilterInterface
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
4. For a dev reset: `just devModeImport <archive>` runs migrations automatically

---

### Cache issues after config changes

After changing service configuration, routes, or Twig templates:

```bash
just app cache:clear
```

Switching a plugin on or off needs no cache clear - it takes effect on the next request:

```bash
just plugin-enable your-plugin   # or plugin-disable
```
