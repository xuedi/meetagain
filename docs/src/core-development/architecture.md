# Architecture

The structural rules of the MeetAgain codebase — layers, dependencies, and the plugin system.

---

## Layer diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    Controllers & Commands                    │
│          (HTTP/CLI entry points, thin delegation)            │
└───────────────────────┬─────────────────────────────────────┘
                        │ depends on
                        ↓
┌─────────────────────────────────────────────────────────────┐
│                         Services                             │
│              (Business logic, readonly classes)              │
└───────────────────────┬─────────────────────────────────────┘
                        │ depends on
                        ↓
┌─────────────────────────────────────────────────────────────┐
│                       Repositories                           │
│            (Data access, query builder methods)              │
└───────────────────────┬─────────────────────────────────────┘
                        │ depends on
                        ↓
┌─────────────────────────────────────────────────────────────┐
│                         Entities                             │
│            (Pure data objects, Doctrine attributes)          │
└─────────────────────────────────────────────────────────────┘
```

Dependencies only flow **downward**. Repositories never call services; services never call
controllers. These rules are enforced automatically on every `just test` run.

---

## Layer responsibility table

| Layer | Responsibility | May use | May NOT use |
|---|---|---|---|
| **Controller** | Thin HTTP/CLI entry point; validates input; renders response | Service, Entity, Form, Repository (sparingly) | Other controllers |
| **Service** | Business logic, orchestration | Repository, Entity, other Services | Controller, Form |
| **Repository** | Database queries | Entity | Service, Controller |
| **Entity** | Doctrine-mapped data object | — (nothing) | Everything else |

Supporting layers:

| Layer | Responsibility |
|---|---|
| **Form** | Form type classes for building and validating forms |
| **Command** | CLI commands; same rules as controllers |
| **EventSubscriber** | React to Symfony framework events (login, response, etc.) |
| **Twig extension** | Presentation helpers for templates |
| **DataFixtures** | Test and dev data; allowed extra flexibility |

---

## Layer dependency rules

### Controllers delegate, never decide

Controllers are thin. They receive a request, call a service, and return a response.
No business logic lives here.

```php
// src/Controller/ManageController.php
class ManageController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $repo,
    ) {}

    #[Route('/manage', name: 'app_manage')]
    public function index(): Response
    {
        return $this->render('manage/index.html.twig', [
            'events' => $this->repo->findUpcomingEventsWithinRange(),
        ]);
    }
}
```

### Services own the logic

Services contain all business logic. They are `readonly` classes with constructor injection.

```php
// src/Service/CleanupService.php
readonly class CleanupService
{
    public function __construct(
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

### Repositories express intent

Repository method names describe *what* you want, not *how* the query works.

```php
// src/Repository/EventRepository.php
public function findUpcomingEventsWithinRange(
    ?DateTimeInterface $start = null,
    ?DateTimeInterface $end = null,
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
```

### Entities are plain data objects

Entities hold data and Doctrine mappings only. No business logic.

```php
// src/Entity/Event.php
#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: EventTypes::class)]
    private EventTypes $type;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    private Collection $rsvps;

    public function __construct()
    {
        $this->rsvps = new ArrayCollection();
    }
}
```

---

## The plugin system

The core application is designed to function without any plugins. Plugins extend behaviour
by implementing interfaces defined in the core — the core never imports plugin namespaces.

### How it works

Core defines filter interfaces with auto-tagging:

```php
// src/Filter/Event/EventFilterInterface.php
#[AutoconfigureTag]
interface EventFilterInterface
{
    public function getPriority(): int;
    public function getEventIdFilter(): ?array;   // null = no filter, [] = block all
    public function isEventAccessible(int $eventId): ?bool;
}
```

Core composes all registered implementations via `#[AutowireIterator]`:

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

A plugin that wants to filter events simply implements `EventFilterInterface` — it
auto-registers with no changes to core code.

### Plugin interface contract

Every plugin must implement `src/Plugin.php`:

```php
interface Plugin
{
    public function getPluginKey(): string;
    public function getMenuLinks(): array;
    public function getEventTile(int $eventId): ?string;
    public function getEventListItemTags(int $eventId): array;
    public function warmCache(WarmCacheType $type, array $ids): void;
    public function getFooterAbout(): ?string;
    public function getMemberPageTop(): ?string;
    public function getAdminSystemLinks(): ?AdminSection;
    public function loadPostExtendFixtures(OutputInterface $output): void;
    public function preFixtures(OutputInterface $output): void;
    public function postFixtures(OutputInterface $output): void;
}
```

Core calls plugins by iterating the registered list — it never references a specific plugin
class directly.

### The golden rule

**Core must never import a plugin namespace.** The filter interface + `#[AutowireIterator]`
pattern is the only correct way for core to receive plugin contributions.

---

## Symfony events and EntityAction

### EventSubscribers for cross-cutting concerns

Use `#[AsEventListener]` when you need to react to Symfony lifecycle events (e.g. login
success, kernel response) without coupling the listener to the trigger:

```php
#[AsEventListener(event: LoginSuccessEvent::class)]
readonly class LoginSubscriber
{
    public function __invoke(LoginSuccessEvent $event): void
    {
        $response = $event->getResponse();
        if ($response === null) {
            return; // stateless API request — no cookie to set
        }
        // set locale cookie, etc.
    }
}
```

### EntityActionDispatcher for entity lifecycle

When a core entity is created, updated, or deleted, `EntityActionDispatcher` notifies all
registered plugins. This avoids Doctrine lifecycle callbacks leaking plugin logic into the
entity layer.

Core controllers call the dispatcher **after flush** — the entity has an ID and the
transaction is complete:

```php
// In a controller or service, after $em->flush():
$this->entityActionDispatcher->dispatch(EntityAction::Created, $event);
```

Plugins implement `EntityActionInterface` to receive these notifications.

---

## Directory tour

| Directory | What lives there |
|---|---|
| `src/Controller/` | HTTP controllers (frontend + admin) |
| `src/Controller/Admin/` | Admin-only controllers, grouped by submenu |
| `src/Service/` | Business logic services (all `readonly`) |
| `src/Repository/` | Doctrine repositories |
| `src/Entity/` | Doctrine-mapped entities and enums |
| `src/Form/` | Symfony form type classes |
| `src/Command/` | CLI commands |
| `src/Security/` | UserChecker, authenticators |
| `src/EventSubscriber/` | Symfony event listeners |
| `src/Filter/` | Filter interfaces and composite services |
| `src/DataFixtures/` | Dev and test data fixtures |
| `src/Twig/` | Twig extensions |
| `templates/` | Twig templates (mirrors controller structure) |
| `translations/` | YAML translation files (en, de, cn) |
| `plugins/` | Optional plugin modules |
| `tests/Unit/` | PHPUnit unit tests |
| `tests/Functional/` | PHPUnit functional (HTTP) tests |
