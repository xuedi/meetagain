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
- **Quality:** PHPStan level 9, Rector, PHP-CS-Fixer, Deptrac

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

### MultiSite Plugin
- 73 PHP files in plugins/multisite/src/
- 12 entities (including 5 junction tables)
- 14 services (including 4 filter implementations)
- 10 repositories
- 6 controllers
- 4 security voters
- 3 event subscribers
- 11 unit tests
- 3 database migrations

---

## Content Filter System Architecture ⭐

**Location:** `src/Filter/` (Reorganized 2026-01-26 from `src/Service/*Filter/`)

The application implements a **composite filter pattern** for content scoping. This allows plugins to restrict which content is visible without core code having any knowledge of plugins.

### Four Filter Domains

```
src/Filter/
├── Event/       # Event filtering
├── Cms/         # CMS page filtering
├── Menu/        # Navigation menu filtering
└── Member/      # User/member filtering
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
**Purpose:** Authentication, authorization (UserChecker, Voters)

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
- Helper methods: `start()`, `tick()`, `stop()`, `getText()`

See [testing.md](testing.md#custom-abstractfixture) for detailed usage.

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
- `EventFilterInterface` - Filter events
- `CmsFilterInterface` - Filter CMS pages
- `MenuFilterInterface` - Filter navigation menus
- `MemberFilterInterface` - Filter user/member lists

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

**Examples in MultiSite Plugin:**
- `GroupCmsMappingRepository::getPlatformCmsIds()` - Unmapped CMS pages
- `GroupMenuMappingRepository::getPlatformMenuIds()` - Unmapped menus
- `GroupEventMappingRepository::getPlatformEventIds()` - Unmapped events

---

### Junction Table Pattern (MultiSite Plugin)

**Purpose:** Map groups to core entities without modifying core schemas.

**Pattern:**
- Store entity ID as INT (not foreign key)
- No foreign key constraint to core tables
- Core entities remain completely unchanged
- Plugin can be removed cleanly

**Structure:**
```php
#[ORM\Entity]
#[ORM\Table(name: 'multisite_group_event')]
class GroupEventMapping {
    #[ORM\ManyToOne(targetEntity: Group::class)]
    private ?Group $group = null;

    #[ORM\Column(name: 'event_id', type: 'integer')]  // NOT a FK!
    private ?int $eventId = null;
}
```

**Junction Tables in MultiSite Plugin:**
- `multisite_group_event` - Maps events
- `multisite_group_cms` - Maps CMS pages
- `multisite_group_menu` - Maps menus (NEW 2026-01-26)
- `multisite_group_location` - Maps locations
- `multisite_group_announcement` - Maps announcements

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

### Current Plugin Architecture Violations (TODO)

**KNOWN ISSUE:** The following files currently violate the "Main code MUST NOT depend on plugin code" principle:

1. `src/Controller/EventController.php:41-42` - Hardcoded `#[Autowire(service: 'Plugin\MultiSite\Service\WhitelabelEventFilterService')]`
2. `src/Controller/ProfileController.php:32-33` - Same violation
3. `src/Service/CmsService.php:17-18` - Same violation

**Status:** Architecture design completed (see plan above). Implementation pending.

**Why This Exists:** Early implementation before plugin content filtering pattern was established.

**Impact:** MultiSite plugin cannot be cleanly removed without code changes. Other plugins cannot provide content filters.

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

- **PHPStan Level 9** - Strictest static analysis, zero tolerance for type issues
- **Rector** - Automated code modernization and PHP version upgrades
- **PHP-CS-Fixer** - Code style enforcement (PSR-12 + custom rules)
- **Deptrac** - Architecture layer validation and dependency enforcement

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
- **Permission Caching** - Voter results cached per request
- **Early Hints** - HTTP/2 asset preloading for faster page loads

---

## Future Improvements

### Filter System Enhancements

1. **Common Base Classes** - Extract shared logic (priority sorting, AND composition)
2. **Generic Filter Interface** - Possible with PHP 8.4+ generics
3. **Filter Decorators** - Caching layer, logging, performance monitoring
4. **Filter Composition Strategies** - OR logic option alongside AND

### Plugin Architecture Evolution

1. **Plugin Marketplace** - Discovery and installation system
2. **Plugin Versioning** - Compatibility checks and dependency management
3. **Plugin Dependencies** - Inter-plugin relationships and load ordering
4. **Plugin Events** - Standardized event system for plugin communication

### Performance & Scalability

1. **Database Views** - Materialized views for complex junction queries
2. **Caching Layer** - Redis cache for filter results and permission checks
3. **Query Optimization** - Batch loading, cursor pagination for large datasets
4. **Async Processing** - Background jobs for heavy operations

---

## Known Architectural Debt

From `tests/deptrac.yaml` skip violations:

1. **MenuRoutes enum references controllers** for route names
   - Location: `src/Entity/MenuRoutes.php`
   - TODO: Refactor to use route string constants

2. **EventSubscriber references controller** for route comparison
   - Location: `src/EventSubscriber/KernelRequestSubscriber.php`
   - TODO: Use route names instead

3. **Twig extension references controller**
   - Location: `src/Twig/RenderImageModalExtension.php`
   - TODO: Refactor reference pattern

4. **Commands with direct repository access**
   - Location: `src/Command/EventAddFixtureCommand.php`, `src/Command/EmailTemplateSeedCommand.php`
   - Reason: Bulk operations, performance

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

## Validation with Deptrac

Run architecture validation:
```bash
just checkDeptrac
```

This enforces all layer dependency rules and fails if violations are introduced.

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

### Platform Content Pattern

```php
// Repository method to find unmapped content
public function getPlatformCmsIds(): array
{
    return $this->em->createQueryBuilder()
        ->select('c.id')
        ->from(Cms::class, 'c')
        ->leftJoin(GroupCmsMapping::class, 'gcm', 'WITH', 'c.id = gcm.cmsId')
        ->where('gcm.id IS NULL')  // Not in junction table = platform
        ->getQuery()
        ->getSingleColumnResult();
}

// Filter implementation
public function getCmsIdFilter(): ?array
{
    $platformIds = $this->mappingRepository->getPlatformCmsIds();

    if (!$context->isActive()) {
        return $platformIds;  // Main domain: platform only
    }

    $groupIds = $this->mappingRepository->getCmsIdsForGroup($context->group);
    return array_merge($groupIds, $platformIds);  // Group + platform
}
```

### Junction Table Query

```php
// Get entity IDs for a group, then query core repository
public function getEventsForGroup(Group $group): array
{
    // Step 1: Query junction table (plugin)
    $eventIds = $this->groupEventMappingRepo->getEventIdsForGroup($group);

    // Step 2: Query core entities (no knowledge of groups)
    return $this->eventRepo->findById($eventIds);
}
```

### Service Layer Pattern

```php
// All services are readonly
readonly class CleanupService
{
    public function __construct(
        private EventRepository $eventRepo,
        private EntityManagerInterface $em,
    ) {}

    public function removeOrphanedImages(): int
    {
        // Business logic here
        $orphaned = $this->imageRepo->findOrphaned();

        foreach ($orphaned as $image) {
            $this->em->remove($image);
        }
        $this->em->flush();

        return count($orphaned);
    }
}
```

---

**Related Documentation:**
- [Conventions](conventions.md) - Coding standards
- [Testing](testing.md) - Testing strategies
- [MultiSite Plugin](../plugins/multisite/ARCHITECTURE.md) - Plugin architecture details
- [Filter Refactoring](refactoring/2026-01-26-filter-reorganization.md) - Filter reorganization details
