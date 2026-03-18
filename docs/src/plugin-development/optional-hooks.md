# Optional Hooks

Plugins implement additional interfaces only for the capabilities they need.
Each interface is auto-registered via `#[AutoconfigureTag]` — no manual service config required.

---

## Capabilities at a glance

| Interface | When to use it | Key method |
|---|---|---|
| `AdminNavigationInterface` | Add sections and links to the admin sidebar | `getAdminNavigationConfig()` |
| `EventFilterInterface` | Control which events are visible | `getEventIdFilter()` |
| `MenuFilterInterface` | Filter or modify navigation links | `filterMenuLinks()` |
| `CmsFilterInterface` | Control which CMS pages are visible | `getCmsPageSlugs()` |
| `MemberFilterInterface` | Filter which members appear in lists | `getUserIds()` |
| `ActionAuthorizationInterface` | Allow or deny user actions | `canPerformAction()` |
| `ActionAuthorizationMessageProviderInterface` | Custom messages for denied actions | `getUnauthorizedMessage()` |
| `EventFilterFormContributorInterface` | Add fields to the event filter form | `addFields()` |
| `NotificationProviderInterface` | Add counts to the notification bell | `getNotifications()` |
| `EntityActionInterface` | React to core entity lifecycle events | `handleEntityAction()` |

---

## Navigation

### AdminNavigationInterface

**Purpose:** Add sections and links to the admin sidebar (the modern approach — replaces the deprecated `getAdminSystemLinks()`).

**File:** `src/Controller/Admin/AdminNavigationInterface.php`

**When called:** When rendering any admin page.

```php
namespace Plugin\YourPlugin\Controller\Admin;

use App\Controller\Admin\AdminNavigationInterface;
use App\ValueObject\AdminNavigationConfig;
use App\ValueObject\AdminLink;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DashboardController extends AbstractController implements AdminNavigationInterface
{
    public function getAdminNavigationConfig(): ?AdminNavigationConfig
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
                    role: 'ROLE_ADMIN', // Optional — restrict this link by role
                ),
            ],
            sectionRole: 'ROLE_ORGANIZER', // Optional — hide entire section by role
        );
    }
}
```

**AdminLink parameters:**
- `label` — Translation key for link text
- `route` — Symfony route name
- `active` — State identifier (used to highlight the active link)
- `role` — Optional role requirement (hides this link if user lacks role)

**AdminNavigationConfig parameters:**
- `section` — Section heading in the sidebar
- `links` — Array of `AdminLink` objects
- `sectionRole` — Optional role requirement (hides the entire section)

---

## Filters

Filter interfaces use AND logic with a priority chain: a filter with `getEventIdFilter()` returning a non-empty array will restrict results to that set. Returning an empty array means "no filtering from this provider."

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

## Authorization

### ActionAuthorizationInterface

**Purpose:** Allow or deny users from performing specific actions.

**File:** `src/Authorization/Action/ActionAuthorizationInterface.php`

**Tag:** `#[AutoconfigureTag('app.action_authorization')]`

**When called:** Before RSVP, comments, photo uploads, and other actions.

**Priority logic:** AND logic — any provider returning `false` denies the action.

```php
namespace Plugin\YourPlugin\Authorization;

use App\Authorization\Action\ActionAuthorizationInterface;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.action_authorization')]
readonly class MembershipAuthorizationProvider implements ActionAuthorizationInterface
{
    public function getPriority(): int
    {
        return 100;
    }

    public function canPerformAction(string $action, int $eventId, ?User $user): ?bool
    {
        // Return:
        //   true  — explicitly allow
        //   false — explicitly deny
        //   null  — no opinion (let other providers decide)

        if ($action === 'event.rsvp') {
            return $this->canUserRsvp($eventId, $user);
        }

        return null;
    }
}
```

**Available actions:**
- `'event.rsvp'` — Toggle RSVP on an event
- `'event.comment'` — Add a comment to an event
- `'event.upload'` — Upload images to an event

---

### ActionAuthorizationMessageProviderInterface

**Purpose:** Provide custom error messages when an action is denied.

**File:** `src/Authorization/Action/ActionAuthorizationMessageProviderInterface.php`

**Tag:** `#[AutoconfigureTag('app.action_authorization_message')]`

**When called:** When an action is denied by `ActionAuthorizationInterface`.

```php
namespace Plugin\YourPlugin\Authorization;

use App\Authorization\Action\ActionAuthorizationMessageProviderInterface;
use App\Authorization\Action\UnauthorizedMessage;
use App\Entity\User;
use App\Enum\FlashMessageType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.action_authorization_message')]
readonly class CustomMessageProvider implements ActionAuthorizationMessageProviderInterface
{
    public function getPriority(): int
    {
        return 100;
    }

    public function getUnauthorizedMessage(string $action, int $eventId, ?User $user): ?UnauthorizedMessage
    {
        if ($this->hasPendingMembership($user, $eventId)) {
            return new UnauthorizedMessage(
                message: 'Your membership is pending approval. Please wait.',
                type: FlashMessageType::Warning,
            );
        }

        return null; // Fall back to default message
    }
}
```

**FlashMessageType values:**
- `FlashMessageType::Success` — Green notification
- `FlashMessageType::Warning` — Orange/yellow notification
- `FlashMessageType::Error` — Red notification
- `FlashMessageType::Info` — Blue notification

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

**Purpose:** Contribute counts to the notification bell in the header.

**File:** `src/Notification/NotificationProviderInterface.php`

**Tag:** `#[AutoconfigureTag('app.notification_provider')]`

**When called:** When rendering the notification menu.

```php
namespace Plugin\YourPlugin\Notification;

use App\Service\Notification\User\NotificationProviderInterface;
use App\ValueObject\NotificationCount;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.notification_provider')]
readonly class PendingApprovalsNotification implements NotificationProviderInterface
{
    public function getNotifications(): array
    {
        $count = $this->pendingRepository->count();

        if ($count === 0) {
            return [];
        }

        return [
            new NotificationCount(
                label: 'Pending Approvals',
                count: $count,
                url: $this->urlGenerator->generate('app_admin_pending'),
                icon: 'fa fa-clock',
            ),
        ];
    }
}
```

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

| Value | Triggered when |
|---|---|
| `EntityAction::MemberCreated` | A new member joins |
| `EntityAction::MemberDeleted` | A member is removed |
| `EntityAction::EventCreated` | A new event is created |
| `EntityAction::EventUpdated` | An event is updated |
| `EntityAction::EventDeleted` | An event is deleted |
| `EntityAction::RsvpAdded` | A user RSVPs to an event |
| `EntityAction::RsvpRemoved` | A user removes their RSVP |

!!! tip
    Return `null` from the `default` branch of your `match` — it signals "nothing to do"
    without throwing an error for unknown future actions.
