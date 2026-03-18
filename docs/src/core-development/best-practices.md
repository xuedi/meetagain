# Best Practices

Patterns that keep the MeetAgain codebase healthy, safe, and maintainable.

---

## Make services `readonly`

All services must be declared `readonly`. Immutable dependencies mean no accidental state
mutation between requests and no hidden shared state.

```php
// ✅ Correct
readonly class EventService
{
    public function __construct(
        private EventRepository $repo,
        private EntityManagerInterface $em,
    ) {}
}

// ❌ Wrong — mutable, allows state to leak between requests
class EventService
{
    private EventRepository $repo;

    public function setRepo(EventRepository $repo): void
    {
        $this->repo = $repo;
    }
}
```

**Exception:** Services that need a per-request memo field (e.g. to cache a computed value
within a single HTTP request) must remove `readonly` from the class declaration while keeping
`readonly` on each injected property:

```php
// ✅ Per-request memo — class-level readonly removed, property-level kept
class GroupContextService
{
    private ?GroupContext $contextMemo = null;   // ← mutable memo field

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}
}
```

---

## Avoid N+1 queries

When a list page renders one DB query per row, response time scales linearly with row count.
Fix it at the repository level with eager loading.

**Identify:** The Symfony Web Profiler shows duplicate queries. A list of 50 events
triggering 51 queries is the tell-tale sign.

**Fix:** `leftJoin` + `addSelect` in the repository method:

```php
// ❌ N+1 — location is lazy-loaded for each event
public function findAll(): array
{
    return $this->createQueryBuilder('e')->getQuery()->getResult();
}

// ✅ One query — location is eagerly loaded
public function findAllWithLocation(): array
{
    return $this->createQueryBuilder('e')
        ->leftJoin('e.location', 'l')
        ->addSelect('l')
        ->getQuery()
        ->getResult();
}
```

For plugin data loaded inside render loops, use the `warmCache()` pattern to batch-load
all IDs before the loop starts. See [Architecture](architecture.md) for the full pattern.

---

## Use enums for domain values

Enums eliminate magic strings and give the type system visibility into valid values.

```php
// ❌ Magic string — typos silently accepted
$event->setType('meeitng');

// ✅ Enum — invalid values are compile errors
$event->setType(EventTypes::Meeting);

// Entity column definition:
#[ORM\Column(enumType: EventTypes::class)]
private EventTypes $type;

// Enum definition:
enum EventTypes: string
{
    case All     = 'all';
    case Meeting = 'meeting';
    case Social  = 'social';
    case Outdoor = 'outdoor';
}
```

Doctrine stores the `string` backing value in the database and reconstructs the enum on read.

---

## Return typed nullables, not empty strings

A `null` return unambiguously signals "nothing here". An empty string is ambiguous — it
might mean "not set", "empty string is valid", or "not implemented yet".

```php
// ❌ Ambiguous
public function getTeaser(): string
{
    return $this->teaser ?? '';
}

// ✅ Clear intent
public function getTeaser(): ?string
{
    return $this->teaser;
}

// Caller checks once:
if ($event->getTeaser() !== null) {
    // render teaser
}
```

---

## Thin controllers

Controllers validate input and delegate. They do not contain business logic.

```php
// ❌ Fat controller — business logic inline
public function create(Request $request): Response
{
    $event = new Event();
    $event->setTitle($request->get('title'));
    $event->setStart(new DateTimeImmutable($request->get('start')));
    // ... 30 more lines of logic
    $this->em->persist($event);
    $this->em->flush();
    $this->mailer->send(...);
    return $this->redirectToRoute('app_event_list');
}

// ✅ Thin controller — delegates to service
public function create(Request $request): Response
{
    $form = $this->createForm(EventType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $this->eventService->create($form->getData());
        return $this->redirectToRoute('app_event_list');
    }

    return $this->render('events/create.html.twig', ['form' => $form]);
}
```

---

## Core never depends on plugins

The filter interface + `#[AutowireIterator]` pattern is the *only* correct way for core to
receive plugin contributions. Core must never reference a specific plugin class.

```php
// ✅ Core composes via interface — zero plugin knowledge
readonly class EventFilterService
{
    public function __construct(
        #[AutowireIterator(EventFilterInterface::class)]
        private iterable $filters,
    ) {}
}

// ❌ Core directly depends on plugin — architectural violation
#[Autowire(service: 'Plugin\SomePlugin\Filter\SomeEventFilter')]
private readonly SomeEventFilter $someFilter;
```

---

## HTML-sanitize CMS content

CMS pages are editable by group organizers (`ROLE_FOUNDER`). Using `|raw` creates an XSS
vector. Always use `|sanitize_html`:

```twig
{# ✅ Safe #}
{{ block.content|sanitize_html('cms.content') }}

{# ❌ XSS risk #}
{{ block.content|raw }}
```

The `cms.content` allowlist is defined in `config/packages/html_sanitizer.yaml` and permits
common formatting tags while stripping all script-related attributes.

---

## Run `just fixMago` before committing

`just fixMago` auto-formats code and runs all Mago quality checks (linter, analyzer, guard).
`just test` will fail if you skip it.

```bash
just fixMago   # Format + quality checks (fast, run often)
just test      # Full suite: unit + functional + quality
```

Make it a habit: write code → `just fixMago` → `just test` → open PR.
