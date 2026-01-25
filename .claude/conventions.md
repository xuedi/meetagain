# Coding Conventions

Standards and patterns for writing code in the MeetAgain application.

---

## PHP Style

### PSR-12 + Strict Types

```php
<?php declare(strict_types=1);

namespace App\Service;

use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class EventService
{
    public function __construct(
        private EventRepository $eventRepo,
        private EntityManagerInterface $em,
    ) {}
}
```

**Key rules:**
- PSR-12 compliant (enforced by PHP-CS-Fixer)
- `declare(strict_types=1)` on every file
- Use `readonly` for services (PHP 8.1+)
- Constructor property promotion
- Minimize comments; omit if code is self-explanatory

---

### PHP 8.4 Features

**Use these:**
- **Array functions:** `array_find()`, `array_any()`, `array_all()`, `array_find_key()`
- **Asymmetric visibility:** For DTOs and value objects (not entities)
- **New HTML5 support:** For template rendering

```php
// Array functions
$firstActive = array_find(
    $users,
    fn($user) => $user->isActive()
);

// Asymmetric visibility (use in DTOs, not Doctrine entities)
class UserDTO
{
    public function __construct(
        public private(set) string $email,
    ) {}
}
```

**Avoid these:**
- **Property hooks:** Cannot be used in Doctrine entities (ORM limitation)
- Property hooks work with reflection but not with Doctrine proxies

---

### Docblocks

**Omit when types are clear:**
```php
// ❌ Don't add redundant docblocks
/**
 * @param string $email
 * @return User
 */
public function findByEmail(string $email): User

// ✅ Types are sufficient
public function findByEmail(string $email): User
```

**Add for collections:**
```php
// ✅ Generic types need documentation
/**
 * @var Collection<int, Event>
 */
#[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'organizer')]
private Collection $events;
```

---

### Value Objects Over Arrays

Prefer value objects instead of complex arrays to avoid verbose type annotations:

```php
// ❌ Avoid - Requires complex annotations
/**
 * @return array{section: string, links: array<int, array{label: string, route: string, active?: string}>}
 */
public function getAdminSystemLinks(): array

// ✅ Better - Self-documenting with value objects
public function getAdminSystemLinks(): ?AdminSection
```

```php
readonly class AdminSection
{
    /**
     * @param list<AdminLink> $links
     */
    public function __construct(
        private string $section,
        private array $links,
    ) {
    }

    public function getSection(): string
    {
        return $this->section;
    }

    /**
     * @return list<AdminLink>
     */
    public function getLinks(): array
    {
        return $this->links;
    }
}

readonly class AdminLink
{
    public function __construct(
        private string $label,
        private string $route,
        private ?string $active = null,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getActive(): ?string
    {
        return $this->active;
    }
}
```

---

### Use Statements

Always use `use` statements, no FQCNs in code:

```php
// ✅ Good
use App\Entity\Event;
use App\Repository\EventRepository;

class EventService
{
    public function find(int $id): Event { }
}

// ❌ Bad
class EventService
{
    public function find(int $id): \App\Entity\Event { }
}
```

---

## Doctrine / Database

### Entity Attributes

Use Doctrine attributes (not annotations):

```php
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
#[ORM\Index(columns: ['start', 'canceled'], name: 'event_start_idx')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $start;
}
```

---

### Enums in Entities

Use backed enums with `enumType`:

```php
// Entity
#[ORM\Column(enumType: EventTypes::class)]
private EventTypes $type;

// Enum
enum EventTypes: string
{
    case All = 'all';
    case Meeting = 'meeting';
    case Social = 'social';
}
```

**Note:** Keep translator logic OUT of enums (current technical debt). Use a separate translator service.

---

### Collections

Always include generic type annotations:

```php
use Doctrine\Common\Collections\Collection;

/**
 * @var Collection<int, User>
 */
#[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'rsvpEvents')]
private Collection $rsvps;

public function __construct()
{
    $this->rsvps = new ArrayCollection();
}
```

---

### Repository Methods

Use intent-revealing names and QueryBuilder:

```php
// ✅ Good - clear intent
public function findUpcomingEventsWithinRange(
    ?DateTimeInterface $start = null,
    ?DateTimeInterface $end = null
): array {
    return $this->createQueryBuilder('e')
        ->where('e.start >= :now')
        ->andWhere('e.canceled = false')
        ->setParameter('now', $start ?? new DateTimeImmutable())
        ->orderBy('e.start', 'ASC')
        ->getQuery()
        ->getResult();
}

// ❌ Bad - generic name
public function getByDate(DateTimeInterface $date): array
```

---

### Avoiding N+1 Queries

Use joins with `addSelect()`:

```php
public function findByEventWithUser(?int $eventId): array
{
    return $this->createQueryBuilder('c')
        ->leftJoin('c.user', 'u')
        ->addSelect('u')  // ← Eager load to avoid N+1
        ->where('c.event = :eventId')
        ->setParameter('eventId', $eventId)
        ->orderBy('c.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}
```

---

### Array Hydration for Performance

When you don't need entity objects, use array hydration:

```php
public function getStatistics(): array
{
    return $this->createQueryBuilder('e')
        ->select('COUNT(e.id) as total', 'e.type')
        ->groupBy('e.type')
        ->getQuery()
        ->getArrayResult();  // ← Returns arrays, not entities
}
```

**Example from TranslationRepository:**
```php
public function getMatrix(): array
{
    $rows = $this->createQueryBuilder('t')
        ->select('t.id', 't.language', 't.placeholder', 't.translation')
        ->orderBy('t.placeholder', 'ASC')
        ->getQuery()
        ->getArrayResult();  // Much faster than findAll()

    // Process array results...
}
```

---

### Migrations

Doctrine migrations are auto-generated:

```bash
just app doctrine:migrations:diff
just app doctrine:migrations:migrate
```

**Review migrations before committing** - sometimes Doctrine generates incorrect diffs.

---

## Frontend

### Template Naming

- **snake_case** for template files
- **Underscore prefix** for template fragments

```
templates/
├── events/
│   ├── index.html.twig       # Full page
│   ├── details.html.twig     # Full page
│   └── _event_card.html.twig # Fragment (reusable)
```

---

### CSS Framework

**Bulma only** - no other CSS frameworks:

```twig
<div class="card">
    <div class="card-content">
        <div class="content">
            {{ event.description }}
        </div>
    </div>
</div>
```

**Icons:** Font Awesome
```twig
<span class="icon">
    <i class="fas fa-calendar"></i>
</span>
```

---

### JavaScript

**Progressive Enhancement - Website must work without JavaScript:**

JavaScript is for UX enhancement only (smoother, faster interactions). The website MUST be fully functional with JavaScript disabled.

```twig
{# ✅ Good - Works with and without JavaScript #}
<a href="{{ path('app_event_toggle_rsvp', {event: event.id}) }}"
   class="button is-primary"
   data-action="ajax-toggle-rsvp">
    RSVP
</a>

{# Without JS: Navigates to controller, does action, redirects back
   With JS: AJAX call intercepts, updates UI without page reload #}
```

**Pattern:**
1. `href` points to controller that performs the action server-side
2. JavaScript intercepts the link and enhances with AJAX
3. If JavaScript fails/disabled, the fallback controller handles it

```javascript
// modal-handler.js - Enhancement, not requirement
document.querySelectorAll('[data-action="ajax-toggle-rsvp"]').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();  // Stop normal navigation

        fetch(link.href, { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                // Update UI without page reload
                link.textContent = data.rsvp ? 'Cancel RSVP' : 'RSVP';
            })
            .catch(() => {
                // If AJAX fails, fallback to normal navigation
                window.location.href = link.href;
            });
    });
});
```

**No inline scripts:**

```twig
{# ❌ Bad - No fallback #}
<button onclick="alert('clicked')">Click</button>

{# ✅ Good - Progressive enhancement #}
<button data-action="show-modal" data-id="{{ event.id }}">View</button>
<script src="{{ asset('js/modal-handler.js') }}"></script>
```

**Library usage:**
- **Date picker:** Flatpickr (see `templates/admin/base.html.twig`)
- **Admin tables:** JSTable for sortable/searchable tables

---

### Form Rendering

```twig
{{ form_start(form) }}
    {{ form_row(form.title) }}
    {{ form_row(form.description) }}
    {{ form_row(form.start) }}
    <div class="field">
        <div class="control">
            <button class="button is-primary">
                <span class="icon">
                    <i class="fas fa-save"></i>
                </span>
                <span>Save</span>
            </button>
        </div>
    </div>
{{ form_end(form) }}
```

---

### Translation Keys

Use lowercase with underscores:

```twig
{{ 'event.title.placeholder'|trans }}
{{ 'user.email.label'|trans }}
{{ 'action.save'|trans }}
```

---

## Plugins

### Plugin Interface Implementation

```php
#[AutoconfigureTag(Plugin::class)]
class DishesPlugin implements Plugin
{
    public function getPluginKey(): string
    {
        return 'dishes';
    }

    public function getMenuLinks(): array
    {
        return [
            new MenuLink(
                'dishes.menu.title',
                MenuRoutes::Dishes,
                'fa-utensils'
            ),
        ];
    }

    public function getEventTile(Event $event): ?PluginTile
    {
        // Return custom tile for event detail page
    }

    public function loadPostExtendFixtures(ObjectManager $manager): void
    {
        // Load plugin-specific test data
    }
}
```

**Key rules:**
- Main code MUST NOT depend on plugin code
- Plugin tables have NO foreign keys to main tables
- Main application must work when plugins are disabled
- Integration points check if plugin is enabled before calling it
- Use `AutoconfigureTag` attribute for auto-registration

**Example: Checking if plugin is enabled:**
```php
// Always check enabled status before calling plugin methods
public function getPluginEventTiles(int $id): array
{
    $enabledPlugins = $this->pluginService->getActiveList();

    foreach ($this->plugins as $plugin) {
        if (!in_array($plugin->getPluginKey(), $enabledPlugins, true)) {
            continue;  // Skip disabled plugins
        }
        // Only call plugin if enabled
        $tile = $plugin->getEventTile($id);
    }
}
```

---

## Security

### Input Validation

Use Symfony Validator:

```php
use Symfony\Component\Validator\Constraints as Assert;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 3, 'max' => 255]),
                ],
            ])
            ->add('start', DateTimeType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\GreaterThan('now'),
                ],
            ]);
    }
}
```

---

### CSRF Protection

Forms automatically include CSRF tokens:

```php
// Form type (automatic)
class EventType extends AbstractType
{
    // CSRF is enabled by default
}

// Controller
if ($form->isSubmitted() && $form->isValid()) {
    // CSRF token was validated automatically
}
```

---

### Authorization

Use Security Voters:

```php
// src/Security/Voter/EventVoter.php
class EventVoter extends Voter
{
    public const EDIT = 'EVENT_EDIT';
    public const DELETE = 'EVENT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE])
            && $subject instanceof Event;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token
    ): bool {
        $user = $token->getUser();

        return match($attribute) {
            self::EDIT => $subject->getOrganizer() === $user,
            self::DELETE => $subject->getOrganizer() === $user,
            default => false,
        };
    }
}

// Controller
$this->denyAccessUnlessGranted('EVENT_EDIT', $event);
```

---

### Authentication

Custom UserChecker validates user status:

```php
// src/Security/UserChecker.php
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user->getStatus() === UserStatus::Pending) {
            throw new PendingApprovalException();
        }
    }
}
```

---

### Sensitive Data

Never commit sensitive data:

```
.env           # Contains real credentials, in .gitignore
.env.dist      # Template with placeholder values, committed
```

```bash
# ❌ Bad
DATABASE_URL="mysql://root:secret@localhost/app"

# ✅ Good (.env.dist)
DATABASE_URL="mysql://user:password@mariadb/database"
```

---

## Performance

### Caching

Use Symfony Cache with tagged invalidation:

```php
readonly class MenuService
{
    public function __construct(
        private TagAwareCacheInterface $cache,
    ) {}

    public function getMenuItems(): array
    {
        return $this->cache->get('menu_items', function() {
            return $this->buildMenuItems();
        });
    }

    public function invalidateMenu(): void
    {
        $this->cache->invalidateTags(['menu']);
    }
}
```

---

### Query Optimization

1. **Use array hydration** when entities not needed
2. **Join with addSelect()** to avoid N+1
3. **Paginate large result sets** (use Paginator)
4. **Index frequently queried columns**

```php
#[ORM\Index(columns: ['start', 'canceled'], name: 'event_start_idx')]
class Event { }
```

---

### Image Processing

Images are processed asynchronously:

```php
// Upload stores original, creates thumbnails later
$image = $this->imageService->upload($file, $user, ImageType::EventUpload);
$this->imageService->createThumbnails($image);  // Async via message queue
```

Thumbnails stored as WebP for optimal size.

---

### HTTP/2 Early Hints

Controllers use early hints for asset preloading:

```php
protected function getResponse(): Response
{
    $response = new Response();
    // Early hints configured in AbstractController
    return $response;
}

public function index(): Response
{
    return $this->render('template.html.twig', [...], $this->getResponse());
}
```

---

**Related Documentation:**
- [Architecture](architecture.md) - Layer patterns
- [Testing](testing.md) - Testing conventions
