# Feature: Admin Module to Controller Migration

Date: 2026-02-03
Model: opus

## Objective

Restructure the admin area by removing the dynamic admin module system and converting to classic Symfony controllers with standard route annotations and a static sidebar navigation configuration.

## Analysis

### Current State

**Core Admin Modules (15 modules, 15 controllers):**

| Section | Module | Controller | Routes |
|---------|--------|------------|--------|
| System | SystemModule | SystemController | 4 routes |
| System | PluginModule | PluginController | 3 routes |
| System | EmailModule | EmailController | 4 routes |
| System | LanguageModule | LanguageController | 4 routes |
| System | AnnouncementModule | AnnouncementController | 4 routes |
| Tables | EventModule | EventController | 6 routes |
| Tables | LocationModule | LocationController | 4 routes |
| Tables | HostModule | HostController | 4 routes |
| Tables | ImageModule | ImageController | 3 routes |
| Tables | UserModule | UserController | 4 routes |
| CMS | CmsModule | CmsController | 9 routes |
| CMS | MenuModule | MenuController | 5 routes |
| Translation | TranslationModule | TranslationController | 4 routes |
| Logs | LogsModule | LogsController | 3 routes |
| Logs | VisitorsModule | VisitorsController | 1 route |

**MultiSite Plugin Admin Modules (7 modules):**

| Section | Module | Controller | Routes |
|---------|--------|------------|--------|
| MultiSite | GroupModule | AdminGroupController, AdminMembersController | 5 routes |
| Group Context | GroupDashboardModule | GroupDashboardController | 1 route |
| Group Context | GroupSettingsModule | GroupSettingsController | 2 routes |
| Group Context | GroupMembersModule | GroupMembersController | 5 routes |
| Group Context | GroupCmsModule | GroupCmsController | routes |
| Group Context | GroupLocationsModule | GroupLocationsController | routes |
| Group Context | GroupEventsModule | GroupEventsController | 2 routes |

**Key Infrastructure Components:**
1. `AdminModuleInterface` - Defines module contract
2. `AdminService` - Collects modules, builds sidebar
3. `AdminModuleRouteLoader` - Dynamically loads routes
4. `AdminExtension` - Twig extension for `get_admin_sections()`
5. `AdminLink`/`AdminSection` - Value objects for sidebar structure
6. `RequiresRole` - Metadata attribute (not enforced at runtime)

### Dependencies
- Sidebar is rendered in `templates/admin/base_navbar.html.twig`
- Route config in `config/routes.yaml` includes `admin_module` type
- Functional tests in `tests/Functional/AdminPagesTest.php` verify URLs
- Unit tests exist for `AdminService` and `AdminModuleRouteLoader`

### Constraints
- All existing URLs must remain unchanged
- All existing functionality must be preserved
- Plugin modules need context-aware visibility (group context)
- Security must use `#[IsGranted]` attributes

## Approach

### High-Level Strategy

1. **Create static navigation configuration** - Replace dynamic sidebar building with YAML-based configuration
2. **Convert core controllers** - Move existing controllers to `src/Controller/Admin/` with `#[Route]` annotations
3. **Update Twig extension** - Replace dynamic `AdminService` calls with static config reader
4. **Convert plugin controllers** - Move plugin modules to `plugins/multisite/src/Controller/Admin/` with routes
5. **Handle plugin context-aware visibility** - Create service for group-context sidebar filtering
6. **Remove legacy infrastructure** - Delete all module system components
7. **Update tests** - Adapt unit tests, keep functional tests unchanged

### Navigation Configuration Design

Create `config/admin_navigation.yaml`:
```yaml
admin_navigation:
    sections:
        system:
            label: 'System'
            priority: 1000
            role: ROLE_ADMIN
            links:
                - { label: 'menu_admin_system', route: 'app_admin_system', active: 'system' }
                - { label: 'menu_admin_plugin', route: 'app_admin_plugin', active: 'plugin' }
                # ... more links
        tables:
            label: 'Tables'
            priority: 800
            role: ROLE_ADMIN
            links:
                - { label: 'menu_admin_event', route: 'app_admin_event', active: 'event' }
                # ... more links
```

### Plugin Extension Point

For plugin sidebar sections, create `AdminNavigationExtensionInterface`:
```php
interface AdminNavigationExtensionInterface
{
    public function getSections(): array;
}
```

Plugins implement this interface and are collected via `#[AutowireIterator]`.

## Implementation Steps

### Step 1: Create Static Navigation Infrastructure
**Files to create:**
- `config/admin_navigation.yaml` - Navigation configuration
- `src/Service/AdminNavigationService.php` - Reads config and builds sidebar

**Changes:**
- Update `src/Twig/AdminExtension.php` to use new `AdminNavigationService`

**Verification:** Admin sidebar still renders correctly with all existing links.

---

### Step 2: Convert System Section Controllers (5 modules)
**Files to create:**
- `src/Controller/Admin/SystemController.php`
- `src/Controller/Admin/PluginController.php`
- `src/Controller/Admin/EmailController.php`
- `src/Controller/Admin/LanguageController.php`
- `src/Controller/Admin/AnnouncementController.php`

**Changes:**
- Copy controller logic from `src/AdminModules/System/*Controller.php`
- Add `#[Route]` annotations with exact same paths
- Add `#[IsGranted('ROLE_ADMIN')]` at class level
- Update template paths if needed (or keep same)

**Verification:** All `/admin/system*` URLs work, functional tests pass.

---

### Step 3: Convert Tables Section Controllers (5 modules)
**Files to create:**
- `src/Controller/Admin/EventController.php`
- `src/Controller/Admin/LocationController.php`
- `src/Controller/Admin/HostController.php`
- `src/Controller/Admin/ImageController.php`
- `src/Controller/Admin/UserController.php`

**Changes:**
- Copy controller logic from `src/AdminModules/Tables/*Controller.php`
- Add `#[Route]` annotations
- Add `#[IsGranted('ROLE_ADMIN')]` at class level

**Verification:** All `/admin/event*`, `/admin/location*`, etc. URLs work.

---

### Step 4: Convert CMS Section Controllers (2 modules)
**Files to create:**
- `src/Controller/Admin/CmsController.php`
- `src/Controller/Admin/MenuController.php`

**Changes:**
- Copy controller logic from `src/AdminModules/Cms/*Controller.php`
- Add `#[Route]` annotations
- Add `#[IsGranted('ROLE_ADMIN')]` at class level

**Verification:** All `/admin/cms*`, `/admin/menu*` URLs work.

---

### Step 5: Convert Translation and Logs Controllers (3 modules)
**Files to create:**
- `src/Controller/Admin/TranslationController.php`
- `src/Controller/Admin/LogsController.php`
- `src/Controller/Admin/VisitorsController.php`

**Changes:**
- Copy controller logic from respective modules
- Add `#[Route]` annotations
- Add `#[IsGranted('ROLE_ADMIN')]` at class level

**Verification:** All `/admin/translation*`, `/admin/logs*`, `/admin/visitors*` URLs work.

---

### Step 6: Update Route Configuration
**Files to modify:**
- `config/routes.yaml` - Remove `admin_module` loader, add Admin controller routes

**Changes:**
```yaml
# Remove:
# admin_modules:
#     resource: .
#     type: admin_module

# Add:
admin_controllers:
    type: attribute
    prefix: /{_locale}
    resource:
        path: ../src/Controller/Admin/
        namespace: App\Controller\Admin
```

**Verification:** `bin/console debug:router` shows all admin routes.

---

### Step 7: Create Plugin Navigation Extension Interface
**Files to create:**
- `src/Service/AdminNavigationExtensionInterface.php`
- Update `src/Service/AdminNavigationService.php` to collect extensions

**Changes:**
- Define interface with `getSections()` method
- Use `#[AutoconfigureTag]` for auto-registration
- Collect via `#[AutowireIterator]` in navigation service

**Verification:** Core sidebar still works, extension point ready for plugins.

---

### Step 8: Move MultiSite Plugin Admin Modules
**Files to modify:**
- `plugins/multisite/src/Controller/Admin/*.php` - Already exists, add routes

**Files to create:**
- `plugins/multisite/src/Service/AdminNavigationExtension.php`
- `plugins/multisite/config/routes/admin.yaml` (if not using attribute routes)

**Changes:**
- Add `#[Route]` annotations to existing controllers
- Create navigation extension implementing core interface
- Handle group-context visibility in extension's `getSections()`

**Verification:** Plugin admin routes work, sidebar shows correctly.

---

### Step 9: Update Templates Path (if needed)
**Files to potentially modify:**
- All templates in `templates/admin_modules/`

**Evaluation:**
- Templates can stay in `templates/admin_modules/` (no change needed)
- OR move to `templates/admin/` for consistency

**Recommended:** Keep templates in place to minimize changes.

---

### Step 10: Remove Legacy Module Infrastructure
**Files to delete:**
- `src/AdminModules/AdminModuleInterface.php`
- `src/Service/AdminService.php`
- `src/Routing/AdminModuleRouteLoader.php`
- `src/Entity/AdminLink.php`
- `src/Entity/AdminSection.php`
- `src/Security/Attribute/RequiresRole.php`
- All `*Module.php` files in `src/AdminModules/`
- All `*Controller.php` files in `src/AdminModules/` (after moving)
- `plugins/multisite/src/AdminModules/` directory

**Changes:**
- Update any remaining imports
- Remove service definitions if manually configured

**Verification:** `just test` passes, no references to deleted files.

---

### Step 11: Update Unit Tests
**Files to modify/delete:**
- `tests/Unit/Service/AdminServiceTest.php` - Delete or adapt
- `tests/Unit/Routing/AdminModuleRouteLoaderTest.php` - Delete

**Files to create:**
- `tests/Unit/Service/AdminNavigationServiceTest.php`

**Verification:** `just testUnit` passes.

---

### Step 12: Final Cleanup and Verification
**Tasks:**
- Run `just fixMago` for code formatting
- Run `just test` for full test suite
- Run `just checkMagoAll` for static analysis
- Verify all functional tests pass
- Manual testing of admin sidebar navigation

## Testing Strategy

### Automated Tests
1. **Functional tests** (`tests/Functional/AdminPagesTest.php`) - Should pass unchanged (same URLs)
2. **New unit tests** for `AdminNavigationService`
3. **Plugin integration tests** - Verify plugin navigation extension works

### Manual Testing Checklist
- [ ] All sidebar sections render correctly
- [ ] All links navigate to correct pages
- [ ] Section ordering matches current behavior
- [ ] Security restrictions work (non-admin cannot access)
- [ ] MultiSite group-context modules show only when appropriate
- [ ] Dynamic group name in sidebar (plugin feature)

### URL Verification
All existing URLs must remain unchanged:
- `/admin/dashboard`
- `/admin/system`
- `/admin/plugin`
- `/admin/email`
- `/admin/language`
- `/admin/announcement`
- `/admin/event`
- `/admin/location`
- `/admin/host`
- `/admin/image`
- `/admin/user`
- `/admin/cms`
- `/admin/menu`
- `/admin/translation`
- `/admin/logs/activity`
- `/admin/logs/system`
- `/admin/visitors`

## Risks & Considerations

### High Risk
1. **Route conflicts** - Ensure no duplicate route names when converting
2. **Security gaps** - Verify `#[IsGranted]` is applied consistently
3. **Plugin compatibility** - MultiSite plugin heavily relies on current system

### Medium Risk
1. **Template paths** - If changed, all `$this->render()` calls need updating
2. **Service dependencies** - Controllers may have different DI requirements
3. **Group context visibility** - Complex logic in plugin modules

### Low Risk
1. **Test coverage** - Existing functional tests cover URLs
2. **Code duplication** - Controllers are already well-structured

### Mitigation Strategies
- Implement in small, testable steps
- Run full test suite after each step
- Keep legacy system working until new system proven
- Use feature flag if needed for gradual rollout

## File Summary

### Files to Create (Core)
| File | Purpose |
|------|---------|
| `config/admin_navigation.yaml` | Static navigation configuration |
| `src/Service/AdminNavigationService.php` | Reads config, builds sidebar |
| `src/Service/AdminNavigationExtensionInterface.php` | Plugin extension point |
| `src/Controller/Admin/SystemController.php` | System settings |
| `src/Controller/Admin/PluginController.php` | Plugin management |
| `src/Controller/Admin/EmailController.php` | Email templates |
| `src/Controller/Admin/LanguageController.php` | Languages |
| `src/Controller/Admin/AnnouncementController.php` | Announcements |
| `src/Controller/Admin/EventController.php` | Events |
| `src/Controller/Admin/LocationController.php` | Locations |
| `src/Controller/Admin/HostController.php` | Hosts |
| `src/Controller/Admin/ImageController.php` | Images |
| `src/Controller/Admin/UserController.php` | Users |
| `src/Controller/Admin/CmsController.php` | CMS pages |
| `src/Controller/Admin/MenuController.php` | Menus |
| `src/Controller/Admin/TranslationController.php` | Translations |
| `src/Controller/Admin/LogsController.php` | Logs |
| `src/Controller/Admin/VisitorsController.php` | Visitors |
| `tests/Unit/Service/AdminNavigationServiceTest.php` | Unit tests |

### Files to Create (Plugin)
| File | Purpose |
|------|---------|
| `plugins/multisite/src/Service/AdminNavigationExtension.php` | Plugin sidebar integration |

### Files to Delete
| File | Reason |
|------|--------|
| `src/AdminModules/AdminModuleInterface.php` | Replaced by standard controllers |
| `src/Service/AdminService.php` | Replaced by AdminNavigationService |
| `src/Routing/AdminModuleRouteLoader.php` | Replaced by attribute routes |
| `src/Entity/AdminLink.php` | No longer needed |
| `src/Entity/AdminSection.php` | No longer needed |
| `src/Security/Attribute/RequiresRole.php` | Replaced by #[IsGranted] |
| `src/AdminModules/System/*.php` (10 files) | Moved to Controller/Admin |
| `src/AdminModules/Tables/*.php` (10 files) | Moved to Controller/Admin |
| `src/AdminModules/Cms/*.php` (4 files) | Moved to Controller/Admin |
| `src/AdminModules/Translation/*.php` (2 files) | Moved to Controller/Admin |
| `src/AdminModules/Logs/*.php` (4 files) | Moved to Controller/Admin |
| `plugins/multisite/src/AdminModules/*.php` (7 files) | Routes added to existing controllers |
| `tests/Unit/Service/AdminServiceTest.php` | Service removed |
| `tests/Unit/Routing/AdminModuleRouteLoaderTest.php` | Loader removed |

### Files to Modify
| File | Changes |
|------|---------|
| `config/routes.yaml` | Remove admin_module, add admin controllers |
| `src/Twig/AdminExtension.php` | Use AdminNavigationService |
| `templates/admin/base_navbar.html.twig` | Minor adjustments if needed |
| `plugins/multisite/src/Controller/Admin/*.php` | Add #[Route] annotations |

---

### Critical Files for Reference

- `/home/xuedi/Projects/meetAgain/src/AdminModules/AdminModuleInterface.php` - Interface to remove, understand contract
- `/home/xuedi/Projects/meetAgain/src/Service/AdminService.php` - Logic to replicate in new service
- `/home/xuedi/Projects/meetAgain/src/Routing/AdminModuleRouteLoader.php` - Route patterns to preserve
- `/home/xuedi/Projects/meetAgain/src/Twig/AdminExtension.php` - Twig integration to update
- `/home/xuedi/Projects/meetAgain/config/routes.yaml` - Route configuration to modify
