# Architecture Documentation

This document describes the architectural patterns and layer dependencies of the MeetAgain application.

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

Enforced by Mago Guard (`just checkMagoGuard`, config: `tests/config/mago.toml`):

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

**Admin Controller Organization:**

Subdirectories under `src/Controller/Admin/` indicate controllers grouped by a shared submenu:

- `Admin/Email/` - Email management (templates, sendlog, announcements, debugging) with shared tabs navigation
- `Admin/Translation/` - Translation management (edit, actions) with shared tabs navigation
- `Admin/Settings/` - Settings pages (config, theme, language, images) with shared tabs navigation

Each group has:
- `LinkController.php` - Provides single main navigation link (e.g., "Email" → System section)
- Other controllers return `null` for `getAdminNavigation()`
- `_tabs_navigation.html.twig` template - Shared submenu included by all pages in the group

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

See [testing.md](testing.md) for fixture usage patterns and practical examples.

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

**Plugin Interface** (`src/Plugin.php`):
```php
interface Plugin
{
    public function getPluginKey(): string;
    public function getMenuLinks(): array;
    public function getEventTile(int $eventId): ?string;
    public function getEventListItemTags(int $eventId): array;
    public function warmCache(WarmCacheType $type, array $ids): void; // batch pre-warm, see Caching Strategy
    public function getFooterAbout(): ?string;
    public function getMemberPageTop(): ?string;
    public function getAdminSystemLinks(): ?AdminSection;
    public function loadPostExtendFixtures(OutputInterface $output): void;
    public function preFixtures(OutputInterface $output): void;
    public function postFixtures(OutputInterface $output): void;
}
```

**Example:** `plugins/dishes/src/Kernel.php`

---

### Plugin Content Filtering Pattern

**Problem:** Plugins need to filter content (events, CMS pages, etc.) without core code knowing about them.

**Filter Domains** (`src/Filter/`):
```
src/Filter/
├── Event/       # Event filtering (frontend + admin)
├── Cms/         # CMS page filtering (frontend + admin)
├── Menu/        # Navigation menu filtering
├── Member/      # User/member filtering (frontend + admin)
└── Admin/       # Admin-specific sub-filters
```

Each domain has three components: **Interface** (`#[AutoconfigureTag]`), **Service** (`#[AutowireIterator]`), **Result** (value object).

**Core defines interfaces with auto-tagging:**
```php
// src/Filter/Event/EventFilterInterface.php
#[AutoconfigureTag]
interface EventFilterInterface
{
    public function getPriority(): int;
    public function getEventIdFilter(): ?array;  // null = no filter, [] = block all, [...] = whitelist
    public function isEventAccessible(int $eventId): ?bool;
}
```

**Core provides composite services using `#[AutowireIterator]`:**
```php
// src/Filter/Event/EventFilterService.php
readonly class EventFilterService
{
    public function __construct(
        #[AutowireIterator(EventFilterInterface::class)]
        private iterable $filters,
    ) {}
}
```

Plugins implement the interface and auto-register via `#[AutoconfigureTag]`. Controllers use only the core service — zero plugin knowledge in core.

**Benefits:** Zero plugin knowledge in core, composable (AND intersection logic), auto-registration, priority ordering, testable in isolation.

**Available Filter Interfaces:**

Frontend: `EventFilterInterface`, `CmsFilterInterface`, `MenuFilterInterface`, `MemberFilterInterface`

Admin: `AdminEventListFilterInterface`, `AdminMemberListFilterInterface`, `AdminCmsListFilterInterface`

Admin filters extend frontend behavior with `getDebugContext()` for access denied logging.

---

### Platform Content Pattern

**Purpose:** Content visible on all domains (not scoped to specific context).

**Concept:** Content NOT in junction table = platform content

**Use Cases:**
- Legal pages (imprint, privacy policy) required on all domains
- Shared footer menus
- Global announcements

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

**Implementation Note:**
See the relevant plugin's `CLAUDE.md` for specific filter implementations.

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
#[Autowire(service: 'Plugin\MyPlugin\Service\MyPluginFilterService')]
private readonly ?object $pluginFilter = null;
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

### Decision Hierarchy

**Prefer Valkey over per-request PHP memos wherever safe.** Apply in this order:

| Strategy | When to use |
|---|---|
| **Valkey (`CacheInterface`)** | Data stable across requests — config values, menu IDs, CMS filter ID sets. Cross-request sharing multiplies the savings. |
| **Per-request PHP property** | Session- or user-scoped data that must not be shared across requests (e.g. current group context, resolved Doctrine entities). |
| **No cache** | Called once per request, or trivially cheap. |

**Never put Doctrine entity objects in Valkey** — they contain proxy state that is not safely serializable. Cache IDs only, then fetch entities fresh on the way out.

---

### Valkey / Symfony `CacheInterface`

Inject `CacheInterface` and use the callback form. Write invalidation must call `$cache->delete(key)` after every flush.

```php
// Reading — callback only runs on cache miss
private function getCachedValue(string $name): ?string
{
    return $this->cache->get(
        'config_' . $name,
        function (ItemInterface $item) use ($name): ?string {
            $item->expiresAfter(3600);
            return $this->repo->findOneBy(['name' => $name])?->getValue();
        },
    );
}

// Writing — always invalidate after flush
public function setString(string $name, string $value): void
{
    // ... persist & flush ...
    $this->cache->delete('config_' . $name);
}
```

**Cache key conventions:**

| Prefix | Data | TTL |
|---|---|---|
| `config_{name}` | Single config value | 24 h |
| `cms_locked_ids` | Locked CMS page IDs | 1 h |
| `cms_menu_location_{n}` | CMS IDs for a menu location | 1 h |
| `menu_{type}_{locale}_{hash}` | Rendered `MenuItem[]` per context | 1 h |

---

### Per-Request PHP Property Memos

Use a nullable private property (not `readonly` class) when the data is user/session scoped and must not leak across requests. Symfony creates a fresh service container per HTTP request, so per-request memos are safe.

**Pattern** — remove `readonly` from the class declaration, keep `readonly` on each injected property:

```php
// ✅ Correct: class-level readonly removed, property-level readonly kept
class GroupContextService
{
    private ?GroupContext $contextMemo = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        // ...
    ) {}

    public function getCurrentContext(): GroupContext
    {
        if ($this->contextMemo !== null) {
            return $this->contextMemo;
        }
        // ... compute ...
        return $this->contextMemo = $result;
    }
}

// ❌ Wrong: readonly class cannot hold mutable memo fields
readonly class GroupContextService { ... }
```

---

### N+1 Avoidance: Plugin `warmCache` Pattern

When a list page calls a plugin method once per row (e.g. `event_list_item_tags`), the naive per-call DB query becomes O(N). Solve this with a single pre-warm call before the render loop.

**`WarmCacheType` enum** (`src/Entity/WarmCacheType.php`):

```php
enum WarmCacheType
{
    case EventListItemTags;
    // Add a new case here when the next list needs warming
}
```

**`Plugin` interface** — one universal method covers all warmable types:

```php
public function warmCache(WarmCacheType $type, array $ids): void;
```

Plugins that don't cache anything leave the body empty. Plugins that do switch on `$type`:

```php
// multisite Kernel.php
public function warmCache(WarmCacheType $type, array $ids): void
{
    if ($type !== WarmCacheType::EventListItemTags) {
        return;
    }
    // One batch query replaces N individual queries
    $groups = $this->groupEventMappingRepository->getGroupsForEventIds($ids);
    foreach ($ids as $id) {
        $this->eventGroupCache[$id] = $groups[$id] ?? null; // null = no group, skip re-query
    }
}
```

**Twig integration** — collect all IDs before the loop, warm once:

```twig
{# Collect IDs #}
{% set allEventIds = [] %}
{% for item in structuredList %}
    {% for event in item.events %}
        {% set allEventIds = allEventIds|merge([event.id]) %}
    {% endfor %}
{% endfor %}
{# One batch warm call, then the render loop fires zero extra queries #}
{% if allEventIds is not empty %}{{ warm_event_list_item_tags(allEventIds) }}{% endif %}

{% for item in structuredList %}...{% endfor %}
```

**Adding a new warmable type:**

1. Add a case to `WarmCacheType`
2. Add a new Twig function in `PluginExtension` (e.g. `warm_member_tags`) that calls `warmCache(WarmCacheType::NewCase, $ids)`
3. Implement in whichever plugin caches that data; all other plugins leave `warmCache` empty

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

### Performance Optimizations

- **Readonly Services** - Zero state, thread-safe, no side effects (exception: per-request memo services — see Caching Strategy)
- **Valkey-first caching** - Config values, menu IDs, CMS filter sets cached in Valkey; per-request memos only for user/session-scoped data
- **QueryBuilder** - Eager loading with `addSelect()` to avoid N+1
- **Batch `warmCache`** - Pre-populate per-request plugin caches before list render loops (one query replaces N)
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
6. **Missing readonly** - All services must be `readonly` unless they hold a per-request memo property (see Caching Strategy)
7. **Mutable services with shared state** - No state that persists across requests; per-request memo fields are the only allowed exception
8. **Caching entities in Valkey** - Only cache scalar IDs; fetch entities fresh to avoid serialization issues
8. **God objects** - Classes with too many responsibilities

---

## Validation with Mago Guard

Run architecture validation:
```bash
just checkMagoGuard
```

This enforces architectural rules and fails if violations are introduced.

---

**Related Documentation:**
- [Conventions](conventions.md) - Coding standards
- [Testing](testing.md) - Testing strategies and fixture patterns
