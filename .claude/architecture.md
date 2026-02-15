# Architecture Documentation

This document describes the architectural patterns and layer dependencies of the MeetAgain application.

**Last Updated:** 2026-01-26

---

## Overview

MeetAgain is a **Symfony 8.0 / PHP 8.4** event management system with a plugin architecture. It follows a layered architecture with strict dependency rules enforced by Deptrac.

**Technology Stack:**
- **Backend:** Symfony 8.0, PHP 8.4, Doctrine ORM 3.x
- **Frontend:** Bulma CSS, Font Awesome, Flatpickr, JSTable
- **Database:** MariaDB
- **Cache:** Valkey/Redis (Symfony Cache)
- **Testing:** PHPUnit 12, DAMA DoctrineTestBundle
- **Quality:** Mago (linter, analyzer, guard, formatter)

**Codebase Statistics** (as of 2026-01-26):

### Core Repository
- 260+ PHP files in src/
- 36 readonly service classes
- 24 repository classes
- 50+ entity classes
- 30+ controller classes
- 18 form types
- 11 Twig extensions
- 10 CLI commands
- 5 event subscribers
- 4 content filter systems
- 20+ unit test suites


## Content Filter System Architecture ⭐

**Location:** `src/Filter/` (Reorganized 2026-01-26 from `src/Service/*Filter/`)

The application implements a **composite filter pattern** for content scoping. This allows plugins to restrict which content is visible without core code having any knowledge of plugins.

### Filter Domains

```
src/Filter/
├── Event/       # Event filtering (frontend)
├── Cms/         # CMS page filtering (frontend)
├── Menu/        # Navigation menu filtering
├── Member/      # User/member filtering (frontend)
└── Admin/       # Admin-specific filters
    ├── Event/   # Admin event list filtering
    ├── Member/  # Admin member list filtering
    └── Cms/     # Admin CMS list filtering
```

### Filter Pattern

Each filter domain has three components:

1. **Interface** - Defines filtering contract (`#[AutoconfigureTag]` for auto-registration)
2. **Service** - Composite service collecting all filters (`#[AutowireIterator]`)
3. **Result** - Value object wrapping filter results

**Benefits:**
- ✅ Zero plugin knowledge in core
- ✅ Multiple plugins can provide filters (composable)
- ✅ Auto-registration via Symfony tags
- ✅ Priority-based ordering
- ✅ AND logic composition (intersection)
- ✅ Testable in isolation

See [Plugin Content Filtering Pattern](#plugin-content-filtering-pattern) for detailed implementation.

---

## Recent Architectural Changes

### 1. Filter System Reorganization (2026-01-26)

**Previous Location:** `src/Service/EventFilter/`, `src/Service/CmsFilter/`, etc.
**New Location:** `src/Filter/Event/`, `src/Filter/Cms/`, etc.

**Rationale:**
- Groups all filtering logic together
- Separates filtering infrastructure from business services
- Makes the pattern more discoverable
- Prepares for future filter abstractions

**Impact:** 14 files updated (9 core, 5 plugin imports), no behavioral changes.

### 2. CMS & Menu Platform Pages (2026-01-26)

**Enhancement:** Content can now be "platform-level" (visible everywhere).

**Pattern:**
- CMS pages/menus NOT mapped to any filter context are "platform content"
- Platform content visible on ALL domains (including whitelabel domains)
- Used for legal pages (imprint, privacy) that should appear everywhere
- Filters automatically merge group content + platform content

**Example:**
```php
// MultiSite WhitelabelCmsFilter
public function getCmsIdFilter(): ?array {
    $platformIds = $this->mappingRepository->getPlatformCmsIds();

    if (!$context->isActive()) {
        return $platformIds;  // Main domain: only platform pages
    }

    $groupIds = $this->mappingRepository->getCmsIdsForGroup($context->group);
    return array_merge($groupIds, $platformIds);  // Group domain: both
}
```

### 3. Menu Filtering System (2026-01-26)

**New Feature:** Navigation menus can now be filtered per context.

**Changes:**
- New entity: `GroupMenuMapping` (junction table)
- New filter: `WhitelabelMenuFilter implements MenuFilterInterface`
- Migration: `Version20260126120000` creates `multisite_group_menu` table
- MenuRepository integrated with `MenuFilterService`

**Impact:**
- Menus can be customized per whitelabel domain
- Platform menus visible everywhere
- Consistent with CMS page filtering pattern

---

## Layer Architecture

The application is organized into distinct layers with clear responsibilities and allowed dependencies.

```
┌─────────────────────────────────────────────────────────────┐
│                    Controllers & Commands                    │
│          (HTTP/CLI entry points, thin delegation)            │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ↓
┌─────────────────────────────────────────────────────────────┐
│                         Services                             │
│              (Business logic, readonly classes)              │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ↓
┌─────────────────────────────────────────────────────────────┐
│                       Repositories                           │
│            (Data access, query builder methods)              │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ↓
┌─────────────────────────────────────────────────────────────┐
│                         Entities                             │
│            (Pure data objects, Doctrine attributes)          │
└─────────────────────────────────────────────────────────────┘
```

---

## Layer Dependency Rules

Enforced by `tests/deptrac.yaml`:

### Controller Layer
**Can depend on:** Service, Entity, Form, Security, Repository
**Purpose:** Thin HTTP entry points that delegate to services

```php
// Example: src/Controller/ManageController.php
class ManageController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $repo,
    ) {}

    #[Route('/manage', name: self::ROUTE_MANAGE)]
    public function index(): Response
    {
        return $this->render('manage/index.html.twig', [
            'events' => $this->repo->findUpcomingEventsWithinRange(),
        ]);
    }
}
```

**Note:** Controllers should delegate to services, not repositories directly (TODO: refactor existing direct repository usage).

---

### Service Layer
**Can depend on:** Repository, Entity
**Purpose:** Business logic, orchestration, readonly classes with constructor injection

```php
// Example: src/Service/CleanupService.php
readonly class CleanupService
{
    public function __construct(
        private EventRepository $eventRepo,
        private ImageRepository $imageRepo,
        private EntityManagerInterface $em,
    ) {}

    public function removeOrphanedImages(): int
    {
        $orphaned = $this->imageRepo->findOrphaned();
        foreach ($orphaned as $image) {
            $this->em->remove($image);
        }
        $this->em->flush();

        return count($orphaned);
    }
}
```

**Key principles:**
- All services MUST be `readonly`
- Use constructor injection only
- Single Responsibility Principle
- No static methods

---

### Repository Layer
**Can depend on:** Entity
**Purpose:** Data access with intent-revealing method names

```php
// Example: src/Repository/EventRepository.php
class EventRepository extends ServiceEntityRepository
{
    public function findUpcomingEventsWithinRange(
        ?DateTimeInterface $start = null,
        ?DateTimeInterface $end = null
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->where('e.start >= :now')
            ->andWhere('e.canceled = false')
            ->setParameter('now', $start ?? new DateTimeImmutable())
            ->orderBy('e.start', 'ASC');

        if ($end !== null) {
            $qb->andWhere('e.start <= :end')
               ->setParameter('end', $end);
        }

        return $qb->getQuery()->getResult();
    }
}
```

**Key principles:**
- Intent-revealing method names (not `getByStartDate()`, use `findUpcomingEventsWithinRange()`)
- Use QueryBuilder, not raw SQL
- Use array hydration for performance when entities not needed
- Avoid N+1 queries (use joins with `addSelect()`)

---

### Entity Layer
**Can depend on:** Nothing
**Purpose:** Pure data objects with Doctrine attributes

```php
// Example: src/Entity/Event.php
#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(enumType: EventTypes::class)]
    private EventTypes $type;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'rsvpEvents')]
    private Collection $rsvps;

    // Getters and setters...
}
```

**Key principles:**
- Use Doctrine attributes (not annotations)
- Enums for status/type fields
- Collections must have docblock: `@var Collection<int, User>`
- No business logic in entities

---

### Supporting Layers

#### Form Layer
**Can depend on:** Entity, Service, Repository
**Purpose:** Form type classes for validation and rendering

#### Command Layer
**Can depend on:** Service, Entity
**Purpose:** CLI commands (similar to controllers)

#### Security Layer
**Can depend on:** Entity, Repository, Service
**Purpose:** Authentication, authorization (UserChecker, #[IsGranted] role checks)

#### Twig Layer
**Can depend on:** Entity, Service
**Purpose:** Twig extensions for presentation helpers

#### EventSubscriber Layer
**Can depend on:** Service, Entity
**Purpose:** React to Symfony events

#### DataFixtures Layer
**Can depend on:** Entity, Repository, Service
**Purpose:** Test data (allowed more flexibility)

**Custom AbstractFixture:**
This project uses a custom `AbstractFixture` base class with type-safe reference methods:

```php
class EventFixture extends AbstractFixture
{
    public function load(ObjectManager $manager): void
    {
        // ✅ Type-safe reference system
        $user = $this->getRefUser('john_doe');
        $location = $this->getRefLocation('office');

        $event = new Event();
        $event->setUser($user);
        $event->setLocation($location);

        $this->addRefEvent('meetup', $event);
    }
}
```

**Benefits:**
- Type-safe magic methods: `getRefUser()`, `addRefEvent()`, etc.
- No constant keys needed
- PHPDoc hints for PHPStan
- Helper methods: `start()`, `stop()`, `getText()`

See [Fixture System Architecture](#fixture-system-architecture) below for comprehensive details.

---

## Fixture System Architecture

### Overview

The application uses a sophisticated fixture system for development and testing data. It features:
- **Custom reference system** with type-safe magic methods
- **Fixture groups** for staged loading (install → base → plugin)
- **Plugin fixture inheritance** for extended entity support
- **Pre/post-fixture hooks** for plugin setup and data transformation

### Inheritance Hierarchy

```
Doctrine\Common\DataFixtures\AbstractFixture (vendor)
       ↓ extends
Doctrine\Bundle\FixturesBundle\Fixture (vendor)
       ↓ extends
App\DataFixtures\AbstractFixture (project)
       ↓ extends (for plugins)
Plugin\MultiSite\DataFixtures\AbstractPluginFixture (plugin)
```

**Key Files:**
- `/src/DataFixtures/AbstractFixture.php` - Base class for core fixtures
- `/plugins/multisite/src/DataFixtures/AbstractPluginFixture.php` - Extended for plugin entities

---

### Custom Reference System

The project implements a type-safe reference system using PHP's `__call()` magic method. Instead of manually managing reference keys, fixtures use magic methods like `getRefUser('name')` and `addRefEvent('name', $entity)`.

**Benefits:**
- Type-safe calls with PHPStan support via `@method` annotations
- No manual key management
- Cross-fixture references work automatically
- Works for both core entities (`App\Entity\*`) and plugin entities (`Plugin\*\Entity\*`)

---

### Plugin Fixture Extension

Plugin fixtures extend `AbstractPluginFixture` which overrides `__call()` to check both plugin and core entity namespaces. This allows plugin fixtures to reference both their own entities (e.g., `getRefGroup()`) and core entities (e.g., `getRefUser()`, `getRefEvent()`).

---

### Fixture Groups & Loading Order

Fixtures are organized into groups for staged loading:

| Group | Purpose | Loaded By |
|-------|---------|-----------|
| `install` | System users, config, languages, email templates | `--group=install` |
| `base` | Core dev data (users, events, locations, CMS, etc.) | `--group=base` |
| `plugin` | Plugin-specific dev data (groups, members, etc.) | `--group=plugin --append` |

**Loading Sequence (`just devModeFixtures`):**

```bash
# 1. Reset database
doctrine:database:drop --force
doctrine:database:create
doctrine:migrations:migrate --no-interaction

# 2. Load base fixtures
doctrine:fixtures:load --group=install        # System users first
doctrine:fixtures:load --append --group=base   # Core dev data

# 3. Run plugin pre-fixture hooks
app:plugin:pre-fixtures  # Plugins can run setup commands before plugin fixtures

# 4. Load plugin fixtures
doctrine:fixtures:load --append --group=plugin
app:plugin:post-fixtures
```

**Key Points:**
- `--append` flag preserves references from previous groups
- Migration runs BETWEEN base and plugin fixtures
- Plugin fixtures can access all base fixture references

---

### Base Fixture Dependency Tree

**Install Group (System Foundation):**
```
SystemUserFixture (provides: import, cron users)
    ↓
├─ LanguageFixture (depends: SystemUserFixture)
├─ ConfigFixture (depends: SystemUserFixture)
└─ EmailTemplateFixture (depends: LanguageFixture)
```

**Base Group (Development Data):**
```
UserFixture (provides: 150+ users)
    ↓
├─ LocationFixture ─┐
├─ HostFixture ─────┼─→ EventFixture (provides: events)
├─ CmsFixture ──────┤
│                   │
├─ ActivityFixture  │
├─ MessageFixture   │
│                   │
└─ CmsBlockFixture ─┴─→ MenuFixture
```

**Execution Order (topologically sorted):**
```
1. SystemUserFixture        (install)
2. LanguageFixture          (install)
3. ConfigFixture            (install)
4. EmailTemplateFixture     (install)
5. UserFixture              (base)
6. LocationFixture          (base)
7. HostFixture              (base)
8. CmsFixture               (base)
9. ActivityFixture          (base)
10. MessageFixture          (base)
11. EventFixture            (base)
12. CmsBlockFixture         (base)
13. MenuFixture             (base)
```

---

### Helper Methods

```php
$this->start();                      // Prints "Creating FixtureName ..."
$this->stop();                       // Prints " OK\n"
$content = $this->getText('file');   // Reads from DataFixtures/FixtureName/file.txt
```

---

### Best Practices

- Use `getDependencies()` to declare fixture dependencies
- Use constants for reference names: `const ADMIN = 'admin';`
- Call `start()` / `stop()` for progress output
- Don't hardcode entity IDs (auto-generated)
- Don't create circular dependencies
- See `testing.md` for detailed fixture examples

---

## Plugin Architecture

The application supports a plugin system for extensibility.

**Key principles:**
- Plugins implement `Plugin` interface
- Main code MUST NOT depend on plugin code
- Plugin tables have no foreign keys to main tables
- Main application must work when plugins are disabled
- Integration points check if plugin is enabled before calling it

**How plugins are called:**
```php
// EventService.php - Integration point example
public function getPluginEventTiles(int $id): array
{
    $enabledPlugins = $this->pluginService->getActiveList();
    $tiles = [];
    foreach ($this->plugins as $plugin) {
        // ✅ Check if plugin is enabled before calling
        if (!in_array($plugin->getPluginKey(), $enabledPlugins, true)) {
            continue;  // Skip disabled plugins
        }
        $tile = $plugin->getEventTile($id);
        if ($tile !== null) {
            $tiles[] = $tile;
        }
    }
    return $tiles;
}
```

**Plugin Interface:**
```php
interface Plugin
{
    public function getPluginKey(): string;
    public function getMenuLinks(): array;
    public function getEventTile(Event $event): ?PluginTile;
    public function loadPostExtendFixtures(ObjectManager $manager): void;
}
```

**Example:** `src/Plugin/Dishes/DishesPlugin.php`

---

### Plugin Content Filtering Pattern

**Problem:** Plugins need to filter content (events, CMS pages, etc.) without core code knowing about them.

**✅ CORRECT - Tagged Service Pattern:**

Core defines interfaces with auto-tagging:
```php
// src/Filter/Event/EventFilterInterface.php
#[AutoconfigureTag]
interface EventFilterInterface
{
    public function getPriority(): int;
    public function getEventIdFilter(): ?array;  // null = no filter, [] = block all, [...] = whitelist
    public function isEventAccessible(int $eventId): ?bool;  // null = no opinion
}
```

Core provides composite services using `#[AutowireIterator]`:
```php
// src/Filter/Event/EventFilterService.php
readonly class EventFilterService
{
    public function __construct(
        #[AutowireIterator(EventFilterInterface::class)]
        private iterable $filters,
    ) {}

    public function getEventIdFilter(): EventFilterResult
    {
        // Compose all filters using AND logic (intersection)
        foreach ($this->getSortedFilters() as $filter) {
            $filterResult = $filter->getEventIdFilter();
            if ($filterResult === null) continue;         // No opinion
            if ($filterResult === []) return emptyResult; // Block all
            // Intersect - event must pass ALL filters
            $resultSet = array_intersect($resultSet, $filterResult);
        }
        return new EventFilterResult($resultSet, $hasActiveFilter);
    }
}
```

Plugins implement the interface:
```php
// plugins/multisite/src/Service/WhitelabelEventFilter.php
readonly class WhitelabelEventFilter implements EventFilterInterface
{
    public function getPriority(): int { return 100; }

    public function getEventIdFilter(): ?array
    {
        if (!$this->contextService->getCurrentContext()->isActive()) {
            return null; // No filtering
        }
        return $this->mappingRepository->getEventIdsForGroup($group);
    }
}
```

Controllers use the core service (no plugin knowledge):
```php
// EventController.php - Clean separation
public function __construct(
    private readonly EventFilterService $eventFilterService, // ✅ Core service
) {}

public function index(): Response
{
    $filterResult = $this->eventFilterService->getEventIdFilter();
    $events = $this->repo->findByFilters(..., $filterResult->getEventIds());
}
```

**Benefits:**
- Zero plugin-specific code in core
- Multiple plugins can provide filters (composable)
- Plugins auto-register via `#[AutoconfigureTag]`
- Priority-based ordering
- AND logic (intersection) - all filters must pass
- Testable in isolation

**Available Filter Interfaces:**

**Frontend Filters:**
- `EventFilterInterface` - Filter events (frontend discovery)
- `CmsFilterInterface` - Filter CMS pages (frontend)
- `MenuFilterInterface` - Filter navigation menus
- `MemberFilterInterface` - Filter user/member lists (frontend)

**Admin Filters:**
- `AdminEventListFilterInterface` - Filter events (admin management)
- `AdminMemberListFilterInterface` - Filter members (admin management)
- `AdminCmsListFilterInterface` - Filter CMS pages (admin management)

Admin filters extend frontend behavior with `getDebugContext()` for logging access denied scenarios.

---

### Platform Content Pattern

**Purpose:** Content visible on all domains (not scoped to specific context).

**Concept:** Content NOT in junction table = platform content

**Use Cases:**
- Legal pages (imprint, privacy policy) required on all domains
- Shared footer menus
- Global announcements
- Company-wide events

**Implementation:**
```php
// Find unmapped content (LEFT JOIN WHERE junction.id IS NULL)
$platformIds = $this->mappingRepository->getPlatformCmsIds();

// Main domain: Show ONLY platform content
if (!$context->isActive()) {
    return $platformIds;
}

// Group domain: Show group content + platform content
$groupIds = $this->mappingRepository->getCmsIdsForGroup($context->group);
return array_merge($groupIds, $platformIds);
```

**Benefits:**
- Single source of truth (no duplication)
- Automatic visibility across all domains
- Easy content management (just don't map it)
- Consistent behavior across filter types

**Implementation Note:**
See plugin documentation for specific implementations (e.g., `plugins/multisite/CLAUDE.md`).

---

### Junction Table Pattern

**Purpose:** Map groups to core entities without modifying core schemas.

**Pattern:**
- Store entity ID as INT (not foreign key)
- No foreign key constraint to core tables
- Core entities remain completely unchanged
- Plugin can be removed cleanly

**Structure:**
```php
#[ORM\Entity]
#[ORM\Table(name: 'plugin_entity_mapping')]
class PluginEntityMapping {
    #[ORM\ManyToOne(targetEntity: PluginEntity::class)]
    private ?PluginEntity $pluginEntity = null;

    #[ORM\Column(name: 'core_entity_id', type: 'integer')]  // NOT a FK!
    private ?int $coreEntityId = null;
}
```

**Example Use Case:**
Plugins that need to associate their entities with core entities can use junction tables to maintain clean separation. See plugin documentation for specific implementations.

**Query Pattern:**
```php
// Get IDs from junction table
$eventIds = $mappingRepo->getEventIdsForGroup($group);

// Query core entities
return $eventRepo->findById($eventIds);
```

**Benefits:**
- Zero modifications to core schema
- Plugin remains truly optional
- Clean uninstall (cascade delete on group removal)
- No risk of orphaned records in core tables

---

### Plugin Integration Points

**Plugin → Core Integration:**

1. **Filter Interfaces** - Plugins implement, core composes via `#[AutowireIterator]`
2. **Plugin Interface** - Standard contract for all plugins (`getPluginKey()`, `getEventTile()`, etc.)
3. **Event Subscribers** - React to Symfony events (Doctrine lifecycle, kernel events)
4. **Service Tags** - Auto-registration via `#[AutoconfigureTag]`
5. **Twig Extensions** - Template helpers for plugin-specific rendering

**Core → Plugin Communication:**

**✅ CORRECT:**
```php
// Core calls plugin via interface
foreach ($this->plugins as $plugin) {
    if (!in_array($plugin->getPluginKey(), $enabledPlugins)) continue;
    $tile = $plugin->getEventTile($id);
}
```

**❌ WRONG:**
```php
// Core depends on specific plugin (architectural violation)
#[Autowire(service: 'Plugin\MultiSite\Service\WhitelabelEventFilterService')]
private readonly ?object $whitelabelFilter = null;
```

---

## Symfony 8 Specific Features

### New in Symfony 8.0 (used in this project)

1. **AssetMapper** - Modern asset management (no Webpack/Encore needed)
2. **HTTP/2 & HTTP/3 Early Hints** - Performance optimization for assets
3. **Scheduler Component** - Cron-like task scheduling
4. **Clock Component** - Time testing utilities
5. **TypedEnum Constraint** - Validates backed enums
6. **RateLimiter Improvements** - Better rate limiting strategies

### Symfony Features in Active Use

**Example: Early Hints**
```php
// Controllers use early hints for asset preloading
#[Route('/event/{id}', name: 'app_event_details')]
public function details(int $id): Response
{
    $response = $this->getResponse(); // Early hints support
    return $this->render('events/details.html.twig', [...], $response);
}
```

**Example: AssetMapper**
```php
// Templates use asset() function with AssetMapper
<link href="{{ asset('styles/app.css') }}" rel="stylesheet">
```

---

## Database Schema Patterns

### Naming Conventions

- **Tables:** snake_case, singular (e.g., `event`, `user`)
- **Columns:** snake_case (e.g., `created_at`, `event_type`)
- **Foreign keys:** `{table}_id` (e.g., `user_id`)
- **Junction tables:** `{table1}_{table2}` (e.g., `event_user` for RSVP)

### Enums

Stored as `VARCHAR` with Doctrine's `enumType`:

```php
#[ORM\Column(enumType: EventTypes::class)]
private EventTypes $type;

enum EventTypes: string
{
    case All = 'all';
    case Meeting = 'meeting';
    case Social = 'social';
}
```

### Translations

Translation system uses `Translation` entity:
- `language` (ISO 639-1 code)
- `placeholder` (translation key)
- `translation` (translated text)
- Unique constraint on `(language, placeholder)`

---

## Caching Strategy

**Symfony Cache (Valkey/Redis):**
- Used for translations, menu items, plugin configuration
- Tagged cache invalidation (e.g., `translations` tag)
- Early hints caching for HTTP/2 performance

**Example:**
```php
$cache->get('menu_items', function() {
    return $this->buildMenuItems();
});

// Invalidation
$cache->invalidateTags(['menu']);
```

---

## Quality Metrics

### Code Quality Tools

- **Mago Linter** - Code quality and PHP 8.4 checks
- **Mago Analyzer** - Static analysis
- **Mago Guard** - Architecture layer validation
- **Mago Formatter** - Code style enforcement

### Testing Strategy

**Core:**
- 20+ unit test suites with comprehensive coverage
- AAA pattern (Arrange-Act-Assert) with clear section comments
- Mock/stub for isolated testing
- DAMA DoctrineTestBundle for transactional tests

**MultiSite Plugin:**
- 11 unit test suites covering all filter implementations
- Junction table repository tests
- Integration tests for group context handling

### Performance Optimizations

- **Readonly Services** - Zero state, thread-safe, no side effects
- **QueryBuilder** - Eager loading with `addSelect()` to avoid N+1
- **Array Hydration** - For list views where entities aren't needed
- **Indexed Junction Tables** - Optimized queries for group mappings
- **Early Hints** - HTTP/2 asset preloading for faster page loads

---

## Known Architectural Debt

1. **Repository in Controller** - Some controllers access repositories directly instead of using services
2. **Commands with direct repository access** - Bulk operations bypass service layer for performance

---

## Anti-Patterns to Avoid

1. **Fat Controllers** - Business logic in controllers
2. **Repository in Controller** - Should use services (architectural debt exists)
3. **Static Methods** - Use dependency injection
4. **Direct DB queries** - Use Doctrine QueryBuilder
5. **Tight coupling** - Respect layer boundaries
6. **Missing readonly** - All services must be readonly
7. **Mutable services** - No state in services
8. **God objects** - Classes with too many responsibilities

---

## Validation with Mago Guard

Run architecture validation:
```bash
just checkMagoGuard
```

This enforces architectural rules and fails if violations are introduced.

---

## Quick Reference

### Content Filtering in Controllers

```php
// Apply filter to repository query
public function index(): Response
{
    $filterResult = $this->eventFilterService->getEventIdFilter();
    $events = $this->eventRepo->findByFilters(..., $filterResult->getEventIds());

    return $this->render('event/index.html.twig', ['events' => $events]);
}
```

### Implementing a Plugin Filter

```php
// In plugin
readonly class MyEventFilter implements EventFilterInterface
{
    public function getPriority(): int {
        return 100; // Higher = earlier in chain
    }

    public function getEventIdFilter(): ?array {
        return [1, 2, 3]; // Whitelist
        // return null;    // No opinion
        // return [];      // Block all
    }

    public function isEventAccessible(int $eventId): ?bool {
        return in_array($eventId, $this->getAllowedIds());
    }
}
```


---

**Related Documentation:**
- [Conventions](conventions.md) - Coding standards
- [Testing](testing.md) - Testing strategies
- [MultiSite Plugin](../plugins/multisite/ARCHITECTURE.md) - Plugin architecture details
- [Filter Refactoring](refactoring/2026-01-26-filter-reorganization.md) - Filter reorganization details
