# Optional Hooks

Plugins implement additional interfaces only for the capabilities they need.
Each interface is auto-registered via `#[AutoconfigureTag]` — no manual service config required.

---

## Capabilities at a glance

| Interface                                     | When to use it                                       | Key method                            |
|-----------------------------------------------|------------------------------------------------------|---------------------------------------|
| `Plugin` (base interface)                     | Serve CSS/JS assets from your plugin                 | `getStylesheets()`, `getJavascripts()` |
| `AdminNavigationInterface`                    | Add sections and links to the admin sidebar          | `getAdminNavigation()`                |
| `EventFilterInterface`                        | Control which events are visible                     | `getEventIdFilter()`                  |
| `MenuFilterInterface`                         | Filter or modify navigation links                    | `filterMenuLinks()`                   |
| `CmsFilterInterface`                          | Control which CMS pages are visible                  | `getCmsPageSlugs()`                   |
| `MemberFilterInterface`                       | Filter which members appear in lists                 | `getUserIds()`                        |
| `EventFilterFormContributorInterface`         | Add fields to the event filter form                  | `addFields()`                         |
| `NotificationProviderInterface`               | Add informational items to the notification bell     | `getNotifications()`                  |
| `ReviewNotificationProviderInterface`         | Add approve/deny items to the review page            | `getReviewItems()`, `approveItem()`, `denyItem()` |
| `EntityActionInterface`                       | React to core entity lifecycle events                | `handleEntityAction()`                |
| `ActivityMetaEnricherInterface`               | Enrich metadata on all activity types                | `enrich()`                            |
| `MessageInterface`                            | Define a new activity type with display rendering    | `getType()`, `validate()`, `render()` |

---

## Plugin Assets

Plugins can serve their own CSS and JavaScript through Symfony AssetMapper by implementing
two methods on the base `Plugin` interface.

### Directory structure

Place assets inside your plugin's `assets/` directory:

```
plugins/your-plugin/
└── assets/
    ├── styles/        ← CSS/SCSS files
    ├── js/            ← JavaScript files
    ├── images/        ← Plugin-specific static images
    └── fonts/         ← Plugin-specific fonts (rare)
```

This directory is auto-discovered by the AssetMapper configuration — no core changes needed.

### Returning asset paths from Kernel.php

```php
public function getStylesheets(): array
{
    return ['styles/myplugin.css'];  // relative to plugins/your-plugin/assets/
}

public function getJavascripts(): array
{
    return ['js/myplugin.js'];  // relative to plugins/your-plugin/assets/
}
```

### How paths resolve

| What you return | File on disk | Logical asset path | `asset()` output |
|---|---|---|---|
| `styles/myplugin.css` | `plugins/your-plugin/assets/styles/myplugin.css` | `plugins/your-plugin/styles/myplugin.css` | `/assets/plugins/your-plugin/styles/myplugin-{hash}.css` |
| `js/myplugin.js` | `plugins/your-plugin/assets/js/myplugin.js` | `plugins/your-plugin/js/myplugin.js` | `/assets/plugins/your-plugin/js/myplugin-{hash}.js` |

The `PluginExtension` Twig service prefixes your paths with `plugins/your-plugin/` automatically.
The base template wraps each path in `{{ asset(...) }}` — you never call `asset()` yourself from `Kernel.php`.

### Referencing plugin images in CSS

From `plugins/your-plugin/assets/styles/myplugin.css`, images are at `../images/`:

```css
.my-icon {
    background-image: url('../images/icon.svg');
}
```

### Referencing plugin images in Twig

```twig
<img src="{{ asset('plugins/your-plugin/images/icon.svg') }}" alt="">
```

---

## Navigation

### AdminNavigationInterface

**Purpose:** Add sections and links to the admin sidebar (the modern approach — replaces the deprecated
`getAdminSystemLinks()`).

**File:** `src/Controller/Admin/AdminNavigationInterface.php`

**When called:** When rendering any admin page.

```php
namespace Plugin\YourPlugin\Controller\Admin;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Controller\Admin\AdminNavigationInterface;
use App\Entity\AdminLink;
use App\Enum\CoreRole;

class DashboardController extends AbstractAdminController implements AdminNavigationInterface
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'Your Plugin',
            links: [
                new AdminLink(
                    label: 'menu_admin_dashboard',
                    route: 'app_admin_plugin_dashboard',
                    active: 'dashboard',
                ),
                new AdminLink(
                    label: 'menu_admin_settings',
                    route: 'app_admin_plugin_settings',
                    active: 'settings',
                    role: CoreRole::Admin, // Optional — restrict this link by role
                ),
            ],
            sectionRole: CoreRole::Organizer, // Optional — hide entire section by role
        );
    }
}
```

**AdminLink parameters:**

- `label` — Translation key for link text
- `route` — Symfony route name
- `active` — State identifier (used to highlight the active link)
- `role` — Optional `RoleInterface` value (hides this link if user lacks that role)

**AdminNavigationConfig parameters:**

- `section` — Section heading in the sidebar
- `links` — Array of `AdminLink` objects
- `sectionRole` — Optional `RoleInterface` value (hides the entire section)

---

## Filters

Filter interfaces use AND logic with a priority chain: a filter with `getEventIdFilter()` returning a non-empty array
will restrict results to that set. Returning an empty array means "no filtering from this provider."

### EventFilterInterface

**Purpose:** Restrict which events are visible to the current user.

**File:** `src/Filter/Event/EventFilterInterface.php`

**Tag:** `#[AutoconfigureTag('app.event_filter')]`

**When called:** Every event query — lists, searches, and detail pages.

```php
namespace Plugin\YourPlugin\Filter;

use App\Filter\Event\EventFilterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.event_filter')]
readonly class PrivateEventFilter implements EventFilterInterface
{
    public function getPriority(): int
    {
        return 100; // Higher runs first
    }

    public function getEventIdFilter(): array
    {
        // Return IDs of events that SHOULD be visible.
        // Empty array = no filtering from this provider.
        return $this->getVisibleEventIds();
    }

    public function isEventAccessible(int $eventId): bool
    {
        return $this->canUserAccessEvent($eventId);
    }
}
```

**Use cases:** Private/public event visibility, group-based access control.

---

### MenuFilterInterface

**Purpose:** Filter or modify navigation links based on context (e.g. active domain, user role).

**File:** `src/Filter/Menu/MenuFilterInterface.php`

**Tag:** `#[AutoconfigureTag('app.menu_filter')]`

**When called:** When rendering the navigation menu.

```php
namespace Plugin\YourPlugin\Filter;

use App\Filter\Menu\MenuFilterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.menu_filter')]
readonly class ContextMenuFilter implements MenuFilterInterface
{
    public function getPriority(): int
    {
        return 100;
    }

    public function filterMenuLinks(array $links): array
    {
        return array_filter($links, fn($link) => $this->shouldShowLink($link));
    }
}
```

**Use cases:** Multi-tenant menu filtering, hiding links by domain or role.

---

### CmsFilterInterface

**Purpose:** Restrict which CMS pages are visible.

**File:** `src/Filter/Cms/CmsFilterInterface.php`

**Tag:** `#[AutoconfigureTag('app.cms_filter')]`

**When called:** When querying CMS pages.

```php
namespace Plugin\YourPlugin\Filter;

use App\Filter\Cms\CmsFilterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.cms_filter')]
readonly class CmsContextFilter implements CmsFilterInterface
{
    public function getPriority(): int
    {
        return 100;
    }

    public function getCmsPageSlugs(): array
    {
        // Return slugs of pages that SHOULD be visible.
        // Empty array = no filtering.
        return $this->getVisiblePageSlugs();
    }
}
```

---

### MemberFilterInterface

**Purpose:** Restrict which members appear in member lists.

**File:** `src/Filter/Member/MemberFilterInterface.php`

**Tag:** `#[AutoconfigureTag('app.member_filter')]`

**When called:** When rendering member lists.

```php
namespace Plugin\YourPlugin\Filter;

use App\Filter\Member\MemberFilterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.member_filter')]
readonly class GroupMemberFilter implements MemberFilterInterface
{
    public function getPriority(): int
    {
        return 100;
    }

    public function getUserIds(): array
    {
        // Return IDs of users that SHOULD be visible.
        // Empty array = no filtering.
        return $this->getVisibleUserIds();
    }
}
```

---

## Permissions and access control

Event-scoped action gating (RSVP, comments, uploads) and custom runtime permission checks
are implemented as standard Symfony voters — no custom interfaces or tags required.

See [Permissions](permissions.md) for the full guide.

---

## Content

### EventFilterFormContributorInterface

**Purpose:** Add custom fields to the event filter form.

**File:** `src/Form/EventFilterFormContributorInterface.php`

**Tag:** `#[AutoconfigureTag('app.event_filter_form_contributor')]`

**When called:** When building the event filter form.

```php
namespace Plugin\YourPlugin\Form;

use App\Form\EventFilterFormContributorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

#[AutoconfigureTag('app.event_filter_form_contributor')]
readonly class CategoryFilterContributor implements EventFilterFormContributorInterface
{
    public function addFields(FormBuilderInterface $builder): void
    {
        $builder->add('category', ChoiceType::class, [
            'label' => 'Category',
            'choices' => [
                'All Categories' => null,
                'Indoor' => 'indoor',
                'Outdoor' => 'outdoor',
            ],
            'required' => false,
        ]);
    }
}
```

---

### NotificationProviderInterface

**Purpose:** Contribute informational items to the notification bell - items that link to a page but need no approve/deny action (e.g. open polls, unread messages).

**File:** `src/Service/Notification/User/NotificationProviderInterface.php`

**When called:** When rendering the notification menu.

```php
namespace Plugin\YourPlugin\Notification;

use App\Entity\User;
use App\Service\Notification\User\NotificationItem;
use App\Service\Notification\User\NotificationProviderInterface;

readonly class MyNotificationProvider implements NotificationProviderInterface
{
    public function getNotifications(User $user): array
    {
        $activePoll = $this->pollService->getActivePoll();
        if ($activePoll === null) {
            return [];
        }

        return [
            new NotificationItem(
                label: 'Open poll - cast your vote!',
                icon: 'fa-vote-yea',
                route: 'app_plugin_myplugin_poll',
            ),
        ];
    }
}
```

---

### ReviewNotificationProviderInterface

**Purpose:** Surface approve/deny items on the central review page at `/profile/review`. The navbar shows a single
consolidated count entry for all pending review items; the review page renders each item with Approve and Deny buttons.

**File:** `src/Service/Notification/User/ReviewNotificationProviderInterface.php`

**When called:** When the navbar count is computed and when the review page renders.

```php
namespace Plugin\YourPlugin\Notification;

use App\Entity\User;
use App\Service\Notification\User\ReviewNotificationItem;
use App\Service\Notification\User\ReviewNotificationProviderInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class MyReviewProvider implements ReviewNotificationProviderInterface
{
    public function getIdentifier(): string
    {
        return 'myplugin.pending_items'; // stable forever - never change after deployment
    }

    /** @return ReviewNotificationItem[] */
    public function getReviewItems(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            return [];
        }

        return array_map(
            static fn($item) => new ReviewNotificationItem(
                id: (string) $item->getId(),
                description: sprintf("Item '%s' pending approval", $item->getName()),
                canDeny: true,
                icon: 'check',
            ),
            $this->repository->findPending(),
        );
    }

    public function approveItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            throw new AccessDeniedException();
        }
        $this->myService->approve((int) $itemId);
    }

    public function denyItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            throw new AccessDeniedException();
        }
        $this->myService->reject((int) $itemId);
    }
}
```

**Key rules:**
- `getIdentifier()` is embedded in form action URLs - never change it once deployed
- Both `approveItem()` and `denyItem()` must check authorisation themselves and throw `AccessDeniedException` if denied
- `itemId` is always cast to/from string; use `(int) $itemId` to recover the DB id

---

## Lifecycle

### EntityActionInterface

**Purpose:** React to core entity lifecycle events — creation, update, deletion.

**File:** `src/Entity/Action/EntityActionInterface.php`

**Tag:** `#[AutoconfigureTag('app.entity_action')]`

**When called:** Whenever a core entity changes state.

```php
namespace Plugin\YourPlugin\Action;

use App\Entity\Action\EntityActionInterface;
use App\Enum\EntityAction;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.entity_action')]
readonly class MembershipActionHandler implements EntityActionInterface
{
    public function handleEntityAction(EntityAction $action, int $entityId): void
    {
        match ($action) {
            EntityAction::MemberCreated => $this->onMemberCreated($entityId),
            EntityAction::MemberDeleted => $this->onMemberDeleted($entityId),
            default => null,
        };
    }
}
```

**EntityAction enum values** — the full list of actions you can react to:

| Value                         | Triggered when            |
|-------------------------------|---------------------------|
| `EntityAction::MemberCreated` | A new member joins        |
| `EntityAction::MemberDeleted` | A member is removed       |
| `EntityAction::EventCreated`  | A new event is created    |
| `EntityAction::EventUpdated`  | An event is updated       |
| `EntityAction::EventDeleted`  | An event is deleted       |
| `EntityAction::RsvpAdded`     | A user RSVPs to an event  |
| `EntityAction::RsvpRemoved`   | A user removes their RSVP |

!!! tip
Return `null` from the `default` branch of your `match` — it signals "nothing to do"
without throwing an error for unknown future actions.

---

## Activity Logging

### Adding activity message types

Plugins define their own activity types by creating message classes. `MessageFactory` auto-discovers
all `MessageInterface` implementations — no service configuration needed.

**File location:** `plugins/<name>/src/Activity/Messages/<ClassName>.php`

**Naming convention:** Type keys follow `<plugin_key>.<action>` (e.g. `bookclub.suggestion_created`).

```php
namespace Plugin\YourPlugin\Activity\Messages;

use App\Activity\MessageAbstract;

class ItemCreated extends MessageAbstract
{
    public const string TYPE = 'yourplugin.item_created';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('item_id');
        $this->ensureIsNumeric('item_id');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Created item #%d', $this->meta['item_id']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Created item <strong>#%d</strong>', $this->meta['item_id']);
    }
}
```

Then call `ActivityService::log()` from your controller after the state-changing action:

```php
$this->activityService->log(ItemCreated::TYPE, $user, ['item_id' => $item->getId()]);
```

!!! warning
When logging destructive actions (delete, reject), read the entity name/title **before**
calling the service method, since the entity may be removed during the operation.

---

### Enriching activity metadata

Implement `ActivityMetaEnricherInterface` to inject context into **all** activity types — for
example, adding the current group or domain to every logged action.

```php
namespace Plugin\YourPlugin\Activity;

use App\Activity\ActivityMetaEnricherInterface;
use App\Entity\User;

readonly class GroupContextEnricher implements ActivityMetaEnricherInterface
{
    public function enrich(string $type, User $user, array $meta): array
    {
        $groupId = $this->resolveCurrentGroupId();
        if ($groupId === null) {
            return [];
        }

        return ['_yourplugin_group_id_' => $groupId];
    }
}
```

**Key rules:**

- Return only the keys to **add**. The original caller's keys always win.
- Use a `_<plugin_key>_` prefix to avoid collisions with other plugins.
- Must not throw — enrichment is best-effort. Failures are logged as warnings.

---

## Email Types

### Adding a custom email type

Implement `EmailInterface` (or `ScheduledEmailInterface` for cron-driven emails) to add a new email type
that automatically appears in the admin template preview and debugging pages.

**File:** `src/Emails/EmailInterface.php` / `src/Emails/ScheduledEmailInterface.php`

```php
namespace Plugin\YourPlugin\Email;

use App\Emails\EmailInterface;
use App\Emails\EmailQueueInterface;
use App\Enum\EmailType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class MyPluginEmail implements EmailInterface
{
    public function __construct(
        private EmailQueueInterface $queue,
        private ConfigService $config,
    ) {}

    public function getIdentifier(): string
    {
        return 'myplugin_custom_email'; // must match EmailType::* value if using core enum
    }

    public function getDisplayMockData(): array
    {
        return [
            'subject' => 'My Plugin Notification',
            'context' => ['username' => 'John Doe', 'host' => 'https://localhost'],
        ];
    }

    public function guardCheck(array $context): bool
    {
        return true; // or implement recipient eligibility checks
    }

    public function send(array $context): void
    {
        $user = $context['user'];

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($user->getLocale());
        $email->context([/* template variables */]);

        $this->queue->enqueue($email, EmailType::MyPluginType);
    }
}
```

Call it by injecting the class directly wherever you need to send it:

```php
$this->myPluginEmail->send(['user' => $user, ...]);
```

For **scheduled emails** (cron-driven), implement `ScheduledEmailInterface` additionally:
- `getDueContexts(DateTimeImmutable $now): DueContext[]` — return what is due now; return `[]` to skip
- `markContextSent(DueContext $context): void` — persist the "sent" state after processing
- `getPlannedItems(DateTimeImmutable $from, DateTimeImmutable $to): ScheduledMailItem[]` — shown on `/admin/email/planned`

`SendScheduledEmailsService` picks up all `ScheduledEmailInterface` implementations automatically via `#[AutowireIterator]`.

!!! note
Plugin email identifiers must not collide with core `EmailType` enum values. If your email type
does not have a corresponding `EmailType` entry, you will need to add one or use a string identifier
and implement the template system separately.
