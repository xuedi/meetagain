# Feature: Admin Module-Based System Redesign

Date: 2026-01-28
Model: opus

## Objective

Completely redesign the admin system to be interface-based and modular, where the admin page sidebar dynamically builds from registered "modules". Each module is a self-contained package that can be registered by core or plugins, with its own routes, controllers, and permissions.

**Key Goals:**
- Interface-based modular design with zero hardcoded features
- Modules register their own routes, sidebar links, and permissions
- Plugin modules work exactly like core modules (no core changes needed)
- Role-based visibility (different users see different sidebar sections)
- Remove global path-based `/admin` restriction
- Keep UI exactly the same - just reorganize the code

## Analysis

### Current Admin System Structure

**Controllers (16 total):**
- Core: `AdminController.php` (dashboard), plus 15 controllers in `src/Controller/Admin/`:
  - `AdminUserController`, `AdminEventController`, `AdminLocationController`
  - `AdminCmsController`, `AdminMenuController`, `AdminSystemController`
  - `AdminPluginController`, `AdminTranslationController`, `AdminEmailController`
  - `AdminLanguageController`, `AdminAnnouncementController`, `AdminImageController`
  - `AdminHostController`, `AdminLogsController`, `AdminVisitorsController`
- Plugin: `AdminGroupController.php` (MultiSite)

**Security:**
- Global path-based access control in `config/packages/security.yaml`:
  ```yaml
  access_control:
    - { path: "^/[a-z]{2}/admin", roles: ROLE_ADMIN }
  ```
- No per-module permissions
- All admin features require `ROLE_ADMIN`

**Sidebar (base_navbar.html.twig):**
- Hardcoded sections: System, Tables, CMS, Translation, Logs
- Plugin links dynamically inserted via `get_plugins_admin_system_links()` Twig function
- Uses `active` variable for highlighting current menu item

**Existing Value Objects:**
- `AdminSection` - Represents a sidebar section with links
- `AdminLink` - Represents a single sidebar link (label, route, active key)
- Used by plugin system already

**Plugin Integration:**
- `Plugin` interface with `getAdminSystemLinks(): ?AdminSection` method
- `PluginExtension.php` collects admin links from enabled plugins
- No permission checking for plugin admin sections

### Key Patterns to Follow

1. **AutoconfigureTag / AutowireIterator** - Used in Filter system (EventFilterInterface, etc.)
2. **Value Objects** - Reuse existing `AdminSection` and `AdminLink` classes
3. **Readonly Services** - All services are readonly classes
4. **Plugin Isolation** - Core never depends on plugin code directly
5. **Pure PHP Modules** - No Symfony attributes in module code

## Approach

Transform the admin system into a **module-based architecture** where:

1. **Dynamic Sidebar** - Sidebar built from registered modules (no hardcoded sections)
2. **Modules** - Each admin feature is a self-contained "module" that registers itself
3. **Auto-Registration** - Modules use `#[AutoconfigureTag]` for automatic discovery
4. **Permission-Based Visibility** - Users only see modules they have permission to access
5. **Plugin-Friendly** - Plugins register modules the same way core does
6. **Route Registration** - Modules define routes, `AdminService` registers them with Symfony
7. **Reuse Existing Classes** - Use `AdminSection` and `AdminLink` value objects

**Core Principle:** The admin system should have ZERO hardcoded features. Everything is a module.

## Architecture Design

### Directory Structure

```
src/
├── AdminModules/
│   ├── AdminModuleInterface.php          # Interface at root
│   ├── System/
│   │   ├── SystemModule.php
│   │   └── SystemController.php
│   ├── Tables/
│   │   ├── EventModule.php
│   │   ├── EventController.php
│   │   ├── LocationModule.php
│   │   ├── LocationController.php
│   │   ├── HostModule.php
│   │   ├── HostController.php
│   │   ├── ImageModule.php
│   │   ├── ImageController.php
│   │   ├── UserModule.php
│   │   └── UserController.php
│   ├── Cms/
│   │   ├── CmsModule.php
│   │   ├── CmsController.php
│   │   ├── MenuModule.php
│   │   └── MenuController.php
│   ├── Translation/
│   │   ├── TranslationModule.php
│   │   └── TranslationController.php
│   └── Logs/
│       ├── LogsModule.php
│       └── LogsController.php
├── Service/
│   └── AdminService.php                  # Main service
├── Routing/
│   └── AdminModuleRouteLoader.php        # Dynamic route registration
└── Twig/
    └── AdminExtension.php                # Sidebar rendering
```

**Plugin Modules:**

```
plugins/multisite/src/
├── AdminModules/
│   └── GroupManagement/
│       ├── GroupManagementModule.php
│       └── GroupManagementController.php
```

### Interface Definitions

**AdminModuleInterface.php:**

```php
<?php declare(strict_types=1);

namespace App\AdminModules;

use App\Entity\AdminLink;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for admin modules.
 * Each module provides a self-contained admin feature.
 */
#[AutoconfigureTag]
interface AdminModuleInterface
{
    /**
     * Unique identifier for this module.
     */
    public function getKey(): string;

    /**
     * Priority for ordering in sidebar (higher = earlier).
     */
    public function getPriority(): int;

    /**
     * Section name for sidebar grouping (e.g., "System", "Tables", "CMS").
     * Modules with the same section name are grouped together.
     */
    public function getSectionName(): string;

    /**
     * Links to show in the sidebar.
     *
     * @return list<AdminLink>
     */
    public function getLinks(): array;

    /**
     * Route definitions for this module.
     *
     * @return array<array{name: string, path: string, controller: array, methods?: string[]}>
     */
    public function getRoutes(): array;

    /**
     * Check if the current user can access this module.
     */
    public function isAccessible(): bool;
}
```

**AdminService.php:**

```php
<?php declare(strict_types=1);

namespace App\Service;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminSection;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Central service for managing admin modules.
 * Collects all modules and builds sidebar structure.
 */
readonly class AdminService
{
    /**
     * @param iterable<AdminModuleInterface> $modules
     */
    public function __construct(
        #[AutowireIterator(AdminModuleInterface::class)]
        private iterable $modules,
    ) {}

    /**
     * Get all sidebar sections for the current user.
     * Returns the exact same structure as the current system.
     *
     * @return list<AdminSection>
     */
    public function getSidebarSections(): array
    {
        $sectionMap = [];

        // Group modules by section name
        foreach ($this->getSortedModules() as $module) {
            if (!$module->isAccessible()) {
                continue;
            }

            $sectionName = $module->getSectionName();

            if (!isset($sectionMap[$sectionName])) {
                $sectionMap[$sectionName] = [];
            }

            // Merge all links from this module
            array_push($sectionMap[$sectionName], ...$module->getLinks());
        }

        // Build AdminSection objects (same as current plugin system)
        $sections = [];
        foreach ($sectionMap as $sectionName => $links) {
            $sections[] = new AdminSection($sectionName, $links);
        }

        return $sections;
    }

    /**
     * Get all modules (used by route loader).
     *
     * @return iterable<AdminModuleInterface>
     */
    public function getAllModules(): iterable
    {
        return $this->modules;
    }

    /**
     * Get all modules sorted by priority.
     *
     * @return array<AdminModuleInterface>
     */
    private function getSortedModules(): array
    {
        $modules = iterator_to_array($this->modules);

        usort(
            $modules,
            static fn(AdminModuleInterface $a, AdminModuleInterface $b): int
                => $b->getPriority() <=> $a->getPriority(),
        );

        return $modules;
    }
}
```

**AdminModuleRouteLoader.php:**

```php
<?php declare(strict_types=1);

namespace App\Routing;

use App\Service\AdminService;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Dynamically loads routes from admin modules.
 * Routes are registered at container compile time (cached).
 */
class AdminModuleRouteLoader extends Loader
{
    private bool $isLoaded = false;

    public function __construct(
        private readonly AdminService $adminService,
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new \RuntimeException('Do not add the "admin_module" loader twice');
        }

        $routes = new RouteCollection();

        foreach ($this->adminService->getAllModules() as $module) {
            foreach ($module->getRoutes() as $routeDefinition) {
                $route = new Route(
                    path: $routeDefinition['path'],
                    defaults: [
                        '_controller' => $routeDefinition['controller'],
                    ],
                    methods: $routeDefinition['methods'] ?? ['GET'],
                );

                $routes->add($routeDefinition['name'], $route);
            }
        }

        $this->isLoaded = true;
        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'admin_module';
    }
}
```

Register in `config/routes.yaml`:
```yaml
admin_modules:
    resource: .
    type: admin_module
```

**AdminExtension.php (Twig):**

```php
<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\AdminSection;
use App\Service\AdminService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminService $adminService,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_admin_sections', $this->getAdminSections(...)),
        ];
    }

    /**
     * @return list<AdminSection>
     */
    public function getAdminSections(): array
    {
        return $this->adminService->getSidebarSections();
    }
}
```

### Example Module Implementation

**TranslationModule.php:**

```php
<?php declare(strict_types=1);

namespace App\AdminModules\Translation;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

readonly class TranslationModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'translation';
    }

    public function getPriority(): int
    {
        return 400; // After System, Tables, CMS
    }

    public function getSectionName(): string
    {
        return 'Translation'; // Same as current sidebar
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(
                label: 'menu_admin_translation_suggestions',
                route: 'app_admin_translation_suggestion',
                active: 'suggestions',
            ),
            new AdminLink(
                label: 'menu_admin_translation_extract',
                route: 'app_admin_translation_extract',
                active: 'extract',
            ),
            new AdminLink(
                label: 'menu_admin_translation_edit',
                route: 'app_admin_translation_edit',
                active: 'edit',
            ),
            new AdminLink(
                label: 'menu_admin_translation_publish',
                route: 'app_admin_translation_publish',
                active: 'publish',
            ),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_translation_suggestion',
                'path' => '/admin/translation/suggestions',
                'controller' => [TranslationController::class, 'suggestions'],
            ],
            [
                'name' => 'app_admin_translation_extract',
                'path' => '/admin/translation/extract',
                'controller' => [TranslationController::class, 'extract'],
            ],
            [
                'name' => 'app_admin_translation_edit',
                'path' => '/admin/translation/edit',
                'controller' => [TranslationController::class, 'edit'],
            ],
            [
                'name' => 'app_admin_translation_publish',
                'path' => '/admin/translation/publish',
                'controller' => [TranslationController::class, 'publish'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ADMIN_TRANSLATION_MANAGE');
    }
}
```

**TranslationController.php:**

```php
<?php declare(strict_types=1);

namespace App\AdminModules\Translation;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ADMIN_TRANSLATION_MANAGE')]
class TranslationController extends AbstractController
{
    public function __construct(
        // Inject services here
    ) {}

    public function suggestions(): Response
    {
        // Extract logic from AdminTranslationController
        return $this->render('admin_modules/translation/suggestions.html.twig', [
            'active' => 'suggestions',
        ]);
    }

    public function extract(): Response
    {
        // ...
        return $this->render('admin_modules/translation/extract.html.twig', [
            'active' => 'extract',
        ]);
    }

    // ... other methods
}
```

### Plugin Module Example

**GroupManagementModule.php (MultiSite Plugin):**

```php
<?php declare(strict_types=1);

namespace Plugin\MultiSite\AdminModules\GroupManagement;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

readonly class GroupManagementModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'multisite_groups';
    }

    public function getPriority(): int
    {
        return 500;
    }

    public function getSectionName(): string
    {
        return 'MultiSite'; // Plugin creates its own section!
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(
                label: 'Groups',
                route: 'app_admin_multisite_groups',
                active: 'multisite_groups',
            ),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_multisite_groups',
                'path' => '/admin/multisite/groups',
                'controller' => [GroupManagementController::class, 'list'],
            ],
            [
                'name' => 'app_admin_multisite_group_new',
                'path' => '/admin/multisite/groups/new',
                'controller' => [GroupManagementController::class, 'new'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_multisite_group_edit',
                'path' => '/admin/multisite/groups/{id}/edit',
                'controller' => [GroupManagementController::class, 'edit'],
                'methods' => ['GET', 'POST'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('MULTISITE_GROUP_MANAGE');
    }
}
```

### Updated Sidebar Template

**templates/admin/base_navbar.html.twig:**

```twig
<aside class="menu">
    {% for section in get_admin_sections() %}
        <p class="menu-label">{{ section.section }}</p>
        <ul class="menu-list">
            {% for link in section.links %}
                <li><a class="{% if active == link.active %}is-active{% endif %}" href="{{ path(link.route) }}">{{ link.label | trans }}</a></li>
            {% endfor %}
        </ul>
    {% endfor %}
</aside>
```

**Changes from current:**
- Changed `get_plugins_admin_system_links()` to `get_admin_sections()`
- Removed hardcoded sections (System, Tables, CMS, Translation, Logs)
- Exact same HTML output - just dynamic instead of hardcoded

### Updated Security Configuration

**config/packages/security.yaml:**

```yaml
security:
    role_hierarchy:
        ROLE_SUPER_ADMIN: [ROLE_ADMIN]
        ROLE_ADMIN:
            - ROLE_MANAGER
            - ADMIN_SYSTEM_MANAGE
            - ADMIN_USER_MANAGE
            - ADMIN_EVENT_MANAGE
            - ADMIN_LOCATION_MANAGE
            - ADMIN_HOST_MANAGE
            - ADMIN_IMAGE_MANAGE
            - ADMIN_CMS_MANAGE
            - ADMIN_MENU_MANAGE
            - ADMIN_TRANSLATION_MANAGE
            - ADMIN_EMAIL_MANAGE
            - ADMIN_LANGUAGE_MANAGE
            - ADMIN_ANNOUNCEMENT_MANAGE
            - ADMIN_LOGS_VIEW
            - ADMIN_PLUGIN_MANAGE
        ROLE_MANAGER:
            - ROLE_USER
            - ADMIN_EVENT_VIEW
            - ADMIN_LOCATION_VIEW

    access_control:
        # Remove global /admin restriction
        # - { path: "^/[a-z]{2}/admin", roles: ROLE_ADMIN }  # REMOVED

        # Each module controller handles its own authorization via #[IsGranted]
        - { path: "^/[a-z]{2}/manage", roles: ROLE_MANAGER }
        - { path: "^/[a-z]{2}/profile", roles: ROLE_USER }
```

## Implementation Steps

### Step 1: Create Core Infrastructure

**Files to create:**
- `src/AdminModules/AdminModuleInterface.php`
- `src/Service/AdminService.php`
- `src/Routing/AdminModuleRouteLoader.php`
- `src/Twig/AdminExtension.php`
- `tests/Unit/Service/AdminServiceTest.php`

**Tasks:**
1. Create `AdminModuleInterface` with `#[AutoconfigureTag]`
2. Implement `AdminService` with `#[AutowireIterator]`
3. Implement `AdminModuleRouteLoader` for dynamic route registration
4. Create `AdminExtension` Twig extension
5. Register route loader in `config/routes.yaml`
6. Add comprehensive unit tests

**Verification:**
- Unit tests pass
- Service can collect and sort modules
- `#[AutoconfigureTag]` auto-registers modules
- Route loader can be instantiated

### Step 2: Create First Module (System)

**Files to create:**
- `src/AdminModules/System/SystemModule.php`
- `src/AdminModules/System/SystemController.php`
- `templates/admin_modules/system/*.html.twig`

**Tasks:**
1. Extract logic from `src/Controller/Admin/AdminSystemController.php`
2. Implement `SystemModule`
3. Move system templates to module directory
4. Test that module appears in sidebar
5. Test that routes work

**Verification:**
- "System" section appears in sidebar
- System links are clickable and work
- Only users with `ADMIN_SYSTEM_MANAGE` see it

### Step 3: Migrate Plugin Management Module

**Files to create:**
- `src/AdminModules/System/PluginModule.php`
- `src/AdminModules/System/PluginController.php`
- `templates/admin_modules/system/plugin*.html.twig`

**Tasks:**
1. Extract from `AdminPluginController`
2. Add to "System" section (same as current)
3. Move templates
4. Test

### Step 4: Migrate Email Module

**Files to create:**
- `src/AdminModules/System/EmailModule.php`
- `src/AdminModules/System/EmailController.php`
- `templates/admin_modules/system/email*.html.twig`

**Tasks:**
1. Extract from `AdminEmailController`
2. Add to "System" section
3. Move templates
4. Test

### Step 5: Migrate Language Module

**Files to create:**
- `src/AdminModules/System/LanguageModule.php`
- `src/AdminModules/System/LanguageController.php`
- `templates/admin_modules/system/language*.html.twig`

**Tasks:**
1. Extract from `AdminLanguageController`
2. Add to "System" section
3. Move templates
4. Test

### Step 6: Migrate Announcement Module

**Files to create:**
- `src/AdminModules/System/AnnouncementModule.php`
- `src/AdminModules/System/AnnouncementController.php`
- `templates/admin_modules/system/announcement*.html.twig`

**Tasks:**
1. Extract from `AdminAnnouncementController`
2. Add to "System" section
3. Move templates
4. Test

### Step 7: Migrate Event Module (Tables Section)

**Files to create:**
- `src/AdminModules/Tables/EventModule.php`
- `src/AdminModules/Tables/EventController.php`
- `templates/admin_modules/tables/event*.html.twig`

**Tasks:**
1. Extract from `AdminEventController`
2. Create "Tables" section
3. Move templates
4. Test

### Step 8: Migrate Location Module

**Files to create:**
- `src/AdminModules/Tables/LocationModule.php`
- `src/AdminModules/Tables/LocationController.php`
- `templates/admin_modules/tables/location*.html.twig`

**Tasks:**
1. Extract from `AdminLocationController`
2. Add to "Tables" section
3. Move templates
4. Test

### Step 9: Migrate Host Module

**Files to create:**
- `src/AdminModules/Tables/HostModule.php`
- `src/AdminModules/Tables/HostController.php`
- `templates/admin_modules/tables/host*.html.twig`

**Tasks:**
1. Extract from `AdminHostController`
2. Add to "Tables" section
3. Move templates
4. Test

### Step 10: Migrate Image Module

**Files to create:**
- `src/AdminModules/Tables/ImageModule.php`
- `src/AdminModules/Tables/ImageController.php`
- `templates/admin_modules/tables/image*.html.twig`

**Tasks:**
1. Extract from `AdminImageController`
2. Add to "Tables" section
3. Move templates
4. Test

### Step 11: Migrate User Module

**Files to create:**
- `src/AdminModules/Tables/UserModule.php`
- `src/AdminModules/Tables/UserController.php`
- `templates/admin_modules/tables/user*.html.twig`

**Tasks:**
1. Extract from `AdminUserController`
2. Add to "Tables" section
3. Move templates
4. Test

### Step 12: Migrate CMS Module

**Files to create:**
- `src/AdminModules/Cms/CmsModule.php`
- `src/AdminModules/Cms/CmsController.php`
- `templates/admin_modules/cms/cms*.html.twig`

**Tasks:**
1. Extract from `AdminCmsController`
2. Create "CMS" section
3. Move templates
4. Test

### Step 13: Migrate Menu Module

**Files to create:**
- `src/AdminModules/Cms/MenuModule.php`
- `src/AdminModules/Cms/MenuController.php`
- `templates/admin_modules/cms/menu*.html.twig`

**Tasks:**
1. Extract from `AdminMenuController`
2. Add to "CMS" section
3. Move templates
4. Test

### Step 14: Migrate Translation Module

**Files to create:**
- `src/AdminModules/Translation/TranslationModule.php`
- `src/AdminModules/Translation/TranslationController.php`
- `templates/admin_modules/translation/*.html.twig`

**Tasks:**
1. Extract from `AdminTranslationController`
2. Create "Translation" section
3. Move templates (4 separate pages)
4. Test all translation routes

### Step 15: Migrate Logs Module

**Files to create:**
- `src/AdminModules/Logs/LogsModule.php`
- `src/AdminModules/Logs/LogsController.php`
- `templates/admin_modules/logs/*.html.twig`

**Tasks:**
1. Extract from `AdminLogsController`
2. Create "Logs" section
3. Move templates (3 log types)
4. Test

### Step 16: Migrate Visitors Module

**Files to create:**
- `src/AdminModules/Logs/VisitorsModule.php`
- `src/AdminModules/Logs/VisitorsController.php`
- `templates/admin_modules/logs/visitors*.html.twig`

**Tasks:**
1. Extract from `AdminVisitorsController`
2. Add to "Logs" section (optional - could be separate)
3. Move templates
4. Test

### Step 17: Update Sidebar Template

**Files to modify:**
- `templates/admin/base_navbar.html.twig`

**Tasks:**
1. Replace hardcoded sections with `get_admin_sections()` loop
2. Remove all hardcoded links
3. Keep exact same HTML structure
4. Test sidebar renders correctly

**Before:**
```twig
<p class="menu-label">System</p>
<ul class="menu-list">
    <li><a href="{{ path('app_admin_plugin') }}">...</a></li>
    ...
</ul>
{% for section in get_plugins_admin_system_links() %}
    ...
{% endfor %}
```

**After:**
```twig
{% for section in get_admin_sections() %}
    <p class="menu-label">{{ section.section }}</p>
    <ul class="menu-list">
        {% for link in section.links %}
            <li><a class="{% if active == link.active %}is-active{% endif %}" href="{{ path(link.route) }}">{{ link.label | trans }}</a></li>
        {% endfor %}
    </ul>
{% endfor %}
```

**Verification:**
- Sidebar looks identical to current
- All sections appear in correct order
- Active states work correctly

### Step 18: Migrate MultiSite Plugin Module

**Files to create:**
- `plugins/multisite/src/AdminModules/GroupManagement/GroupManagementModule.php`
- `plugins/multisite/src/AdminModules/GroupManagement/GroupManagementController.php`

**Files to modify:**
- `plugins/multisite/src/Kernel.php` (implement new interface)

**Tasks:**
1. Create `GroupManagementModule`
2. Move group admin logic to module controller
3. Test that "MultiSite" section appears
4. Test group management functionality

**Verification:**
- MultiSite section appears when plugin enabled
- Only visible to users with `MULTISITE_GROUP_MANAGE`
- All group CRUD operations work

### Step 19: Remove Legacy Code

**Files to remove:**
- `src/Controller/Admin/AdminSystemController.php`
- `src/Controller/Admin/AdminPluginController.php`
- `src/Controller/Admin/AdminEmailController.php`
- `src/Controller/Admin/AdminLanguageController.php`
- `src/Controller/Admin/AdminAnnouncementController.php`
- `src/Controller/Admin/AdminEventController.php`
- `src/Controller/Admin/AdminLocationController.php`
- `src/Controller/Admin/AdminHostController.php`
- `src/Controller/Admin/AdminImageController.php`
- `src/Controller/Admin/AdminUserController.php`
- `src/Controller/Admin/AdminCmsController.php`
- `src/Controller/Admin/AdminMenuController.php`
- `src/Controller/Admin/AdminTranslationController.php`
- `src/Controller/Admin/AdminLogsController.php`
- `src/Controller/Admin/AdminVisitorsController.php`
- Old templates in `templates/admin/` (except base templates)

**Tasks:**
1. Remove all old controllers
2. Remove old templates
3. Search for and update any remaining imports
4. Run full test suite

**Verification:**
- No references to old controllers remain
- All admin functionality works through modules

### Step 20: Update Security Configuration

**Files to modify:**
- `config/packages/security.yaml`

**Tasks:**
1. Remove global `/admin` path restriction
2. Add granular permissions to role hierarchy
3. Define permission sets for ROLE_ADMIN, ROLE_MANAGER
4. Test with different user roles

**Verification:**
- ROLE_ADMIN sees all modules
- ROLE_MANAGER sees limited set
- Custom roles work with specific module permissions

### Step 21: Run Full Test Suite

**Tasks:**
1. Run `just fixMago` to format all new code
2. Run `just test` to verify all tests pass
3. Test manually with different user roles
4. Verify plugin modules work
5. Check sidebar rendering with various permission sets
6. Verify all routes work correctly

**Verification:**
- All unit tests pass
- All functional tests pass
- Manual testing confirms correct behavior
- No regressions in existing functionality
- UI looks and functions exactly the same

## Testing Strategy

### Unit Tests

**AdminServiceTest:**
- Test module collection from multiple implementations
- Test priority-based sorting
- Test filtering by accessibility
- Test section grouping (multiple modules in same section)
- Test empty sidebar (no accessible modules)

**AdminModuleRouteLoaderTest:**
- Test route collection from modules
- Test route registration with correct paths
- Test route methods (GET, POST, etc.)

**Individual Module Tests:**
- Test `isAccessible()` logic with different security contexts
- Test links generation
- Test routes definition
- Test section name

### Integration Tests

**Sidebar Rendering:**
- Test sidebar contains correct modules for ROLE_ADMIN
- Test sidebar contains limited modules for ROLE_MANAGER
- Test sidebar excludes inaccessible modules
- Test section ordering
- Test link ordering within sections

**Module Registration:**
- Test core modules are auto-registered
- Test plugin modules are registered when enabled
- Test plugin modules are excluded when disabled

### Functional Tests

**Admin Route Access:**
- Test each module route is accessible with correct permissions
- Test routes are blocked without required permissions
- Test ROLE_SUPER_ADMIN can access everything

**Plugin Integration:**
- Test plugin modules appear in sidebar
- Test plugin modules respond correctly
- Test plugin modules respect permissions

## Migration Strategy

### Phase 1 - Foundation (Steps 1-2)

- Build core infrastructure (interface, service, route loader)
- Create first module as proof of concept
- Keep existing admin system fully functional
- Test infrastructure without disrupting production

### Phase 2 - Core Modules Migration (Steps 3-16)

- Migrate one module at a time
- Keep old controller working until module is tested
- Test each module individually
- System section → Tables section → CMS section → Translation section → Logs section

### Phase 3 - Sidebar Update (Step 17)

- Update sidebar template to use dynamic rendering
- All modules should be migrated at this point
- Sidebar should look identical to current

### Phase 4 - Plugin Migration (Step 18)

- Migrate MultiSite plugin as proof of concept
- Test plugin module integration
- Document migration path for other plugins

### Phase 5 - Cleanup (Steps 19-21)

- Remove all legacy admin code
- Remove old templates
- Update security configuration
- Final testing and verification

## Risks & Considerations

### Risks

1. **Route conflicts** - New module routes may conflict with existing routes
   - **Mitigation:** Use module-specific route prefixes like `/admin/translation/`, `/admin/tables/event`

2. **Template organization** - Moving templates breaks existing extends/includes
   - **Mitigation:** Use absolute template paths, search/replace all references

3. **Permission complexity** - Many granular permissions may confuse administrators
   - **Mitigation:** Provide sensible role hierarchy defaults, document permission system

4. **Plugin compatibility** - Existing plugins using old system need migration
   - **Mitigation:** Keep backward compatibility initially, provide migration guide

5. **Performance overhead** - Collecting modules on every admin page load
   - **Mitigation:** Routes are cached (compiled), sidebar could be cached per role

6. **Testing coverage** - Large refactoring may introduce subtle bugs
   - **Mitigation:** Comprehensive test suite, gradual rollout, thorough manual testing

### Considerations

**Module Grouping:**
- Multiple modules can share the same section (e.g., System section has 5 modules)
- Sections are created organically by module section names
- No predefined section list

**Module Dependencies:**
- Consider adding dependency system in future (e.g., event edit requires event view)
- Not in initial implementation - keep it simple

**Route Caching:**
- Symfony caches routes at container compile time
- No performance impact from dynamic route registration
- Clear cache when adding new modules during development

**Backward Compatibility:**
- Plugin interface could support both old and new methods temporarily
- Allow gradual migration of plugins over 2-3 releases

**Audit Trail:**
- Consider adding audit logging for module access in future
- Track which users access which modules

## Benefits

### For Core Development

1. **Modularity** - Each module is self-contained and independent
2. **Testability** - Modules can be tested in isolation
3. **Maintainability** - Changes to one module don't affect others
4. **Discoverability** - All module code lives in `AdminModules/` directory
5. **Consistency** - All modules follow the same pattern

### For Plugin Development

1. **Zero Core Changes** - Plugins register modules without modifying core
2. **First-Class Citizens** - Plugin modules work exactly like core modules
3. **Clean Separation** - Plugin code completely isolated from core
4. **Easy Distribution** - Modules are self-contained packages
5. **Same Pattern** - Plugins use the same interface as core

### For Users

1. **Role-Based UI** - Users only see modules relevant to their role
2. **Consistent Experience** - All admin features follow same patterns
3. **Flexible Permissions** - Fine-grained control over admin access
4. **Extensibility** - Easy to add new modules as needs evolve
5. **Identical UI** - No learning curve, looks exactly the same

## Critical Files

### Files to Create
- `src/AdminModules/AdminModuleInterface.php` - Main interface
- `src/Service/AdminService.php` - Central service
- `src/Routing/AdminModuleRouteLoader.php` - Dynamic route registration
- `src/Twig/AdminExtension.php` - Sidebar rendering
- 16+ module implementations in `src/AdminModules/*/`

### Files to Modify
- `templates/admin/base_navbar.html.twig` - Update sidebar rendering
- `config/routes.yaml` - Register route loader
- `config/packages/security.yaml` - Update access control
- `plugins/multisite/src/Kernel.php` - Add module support

### Files to Remove
- All controllers in `src/Controller/Admin/`
- Old admin templates

## Next Steps

After plan approval:
1. Begin with Step 1 (Core Infrastructure)
2. Stop and ask for approval before proceeding to Step 2
3. Continue step-by-step with approval gates between major milestones
4. Unless user says "do it all in one go"

---

**Summary:**
This plan transforms the admin system from hardcoded sections to a fully modular, interface-based architecture using the `AdminModuleInterface` pattern. The UI remains identical while the code becomes much more organized, testable, and plugin-friendly.
