# Plugin Development Guide

Complete reference for developing plugins for the meetAgain platform.

---

## Table of Contents

1. [Core Plugin Interface](#core-plugin-interface)
2. [Optional Interfaces](#optional-interfaces)
3. [Quick Start](#quick-start)
4. [Plugin Architecture](#plugin-architecture)

---

## Core Plugin Interface

Every plugin must implement the `App\Plugin` interface. This interface defines hooks that integrate your plugin with the core application.

### Required Implementation

```php
namespace Plugin\YourPlugin;

use App\Plugin;

class Kernel implements Plugin
{
    public function getPluginKey(): string
    {
        return 'your-plugin'; // Unique identifier
    }

    // ... implement all other methods
}
```

---

### Hook Methods

#### `getPluginKey(): string`

**Purpose:** Returns a unique identifier for your plugin.

**When Called:** During plugin registration and service container building.

**Example:**
```php
public function getPluginKey(): string
{
    return 'dishes'; // Must match directory name
}
```

---

#### `getMenuLinks(): array`

**Purpose:** Add links to the main navigation menu.

**When Called:** Every page load when rendering the navigation bar.

**Returns:** `array<Link>` - List of navigation links.

**Link Parameters:**
- `slug` - URL for the link
- `name` - Translation key (prefixed with `menu_` when rendering)
- `priority` - Optional integer for ordering (default: 0)
  - **Lower values appear on the left**
  - **Higher values appear on the right**
  - Core navigation uses: Events (100), Members (200), Groups (250), Admin (300)

**Example (Simple Link without Priority):**
```php
public function getMenuLinks(): array
{
    return [
        new Link(
            slug: $this->urlGenerator->generate('app_plugin_dishes'),
            name: 'dishes'
        ),
    ];
}
```

**Example (Link with Priority):**
```php
public function getMenuLinks(): array
{
    return [
        new Link(
            slug: $this->urlGenerator->generate('app_plugin_dishes'),
            name: 'dishes',
            priority: 150  // Appears between Members (200) and Events (100)
        ),
    ];
}
```

**Example (Multiple Links - Film Club):**
```php
public function getMenuLinks(): array
{
    return [
        new Link(
            slug: $this->urlGenerator->generate('app_filmclub_filmlist'),
            name: 'films',
            priority: 120
        ),
        new Link(
            slug: $this->urlGenerator->generate('app_filmclub_vote'),
            name: 'vote',
            priority: 130
        ),
    ];
}
```

**Return empty array if no menu links:**
```php
public function getMenuLinks(): array
{
    return [];
}
```

**Priority Recommendations:**
- **0-99:** Plugin links that should appear before Events
- **100-199:** Between Events and Members
- **200-249:** Between Members and Groups
- **250-299:** Between Groups and Admin
- **300+:** After Admin (rarely used)

---

#### `getEventTile(int $eventId): ?string`

**Purpose:** Add a custom tile/box to the event details page.

**When Called:** When rendering event details page (`/event/{id}`).

**Returns:** Rendered HTML string or `null` if nothing to display.

**Example (Film Club - Voting Tile):**
```php
public function getEventTile(int $eventId): ?string
{
    $vote = $this->voteRepository->findByEventId($eventId);

    return $this->twig->render('@Filmclub/tile/event.html.twig', [
        'vote' => $vote,
        'eventId' => $eventId,
    ]);
}
```

**Return null if not applicable:**
```php
public function getEventTile(int $eventId): ?string
{
    return null; // This plugin doesn't add event tiles
}
```

---

#### `getEventListItemTags(int $eventId): array`

**Purpose:** Add badges/tags to events in list views.

**When Called:** When rendering event lists (homepage, search results, etc.).

**Returns:** `array<EventListItemTag>` - List of tags to display.

**Example:**
```php
public function getEventListItemTags(int $eventId): array
{
    $dish = $this->dishRepository->findByEventId($eventId);

    if ($dish && $dish->isVegetarian()) {
        return [
            new EventListItemTag(
                text: 'Vegetarian',
                color: 'is-success',
                icon: 'fa fa-leaf',
                url: null
            ),
        ];
    }

    return [];
}
```

**Tag Properties:**
- `text` - Display label
- `color` - Bulma color class: `is-info`, `is-success`, `is-warning`, `is-danger`
- `icon` - FontAwesome icon class
- `url` - Optional link (null for non-clickable tags)

---

#### `getMemberPageTop(): ?string`

**Purpose:** Add content to the top of the admin member list page.

**When Called:** When rendering `/admin/members`.

**Returns:** Rendered HTML string or `null`.

**Use Case:** Display notifications, pending actions, or important member-related info.

**Example:**
```php
public function getMemberPageTop(): ?string
{
    $pendingApprovals = $this->memberRepository->findPendingApprovals();

    if (count($pendingApprovals) === 0) {
        return null;
    }

    return $this->twig->render('@YourPlugin/admin/pending_notice.html.twig', [
        'pending' => $pendingApprovals,
    ]);
}
```

---

#### `getFooterAbout(): ?string`

**Purpose:** Add custom content to the footer's "About" section.

**When Called:** Every page load when rendering the footer.

**Returns:** Rendered HTML string or `null`.

**Use Case:** Copyright notices, branding, additional info.

**Example:**
```php
public function getFooterAbout(): ?string
{
    return $this->twig->render('@YourPlugin/footer/about.html.twig');
}
```

---

### Fixture Hooks

#### `preFixtures(OutputInterface $output): void`

**Purpose:** Run tasks BEFORE plugin fixtures are loaded.

**When Called:** By `app:plugin:pre-fixtures` command, after base fixtures load.

**Use Case:** Data migration, schema preparation, one-time setup.

**Example:**
```php
public function preFixtures(OutputInterface $output): void
{
    $output->writeln('Running migration...');
    // Check if migration needed
    // Run migration command
}
```

**Leave empty if not needed:**
```php
public function preFixtures(OutputInterface $output): void
{
    // No pre-fixture tasks for this plugin
}
```

---

#### `loadPostExtendFixtures(OutputInterface $output): void`

**Purpose:** Create fixture data that depends on extended events.

**When Called:** By `app:event:add-fixture` command, after events have been extended with recurring instances.

**Use Case:** Creating data linked to future event instances.

**Example (Film Club - Create Votes for Events):**
```php
public function loadPostExtendFixtures(OutputInterface $output): void
{
    $pastEvents = $this->eventRepository->getPastEvents(10);

    foreach ($pastEvents as $event) {
        $vote = new Vote();
        $vote->setEventId($event->getId());
        $vote->setClosesAt(new DateTimeImmutable($event->getStart()->format('Y-m-d H:i:s') . ' -1 day'));
        $this->em->persist($vote);
    }

    $this->em->flush();
    $output->writeln('<info>Created votes for past events</info>');
}
```

---

#### `postFixtures(OutputInterface $output): void`

**Purpose:** Run tasks AFTER all fixtures are loaded.

**When Called:** By `app:plugin:post-fixtures` command, after `doctrine:fixtures:load`.

**Use Case:** Post-processing, data cleanup, configuration.

**Example:**
```php
public function postFixtures(OutputInterface $output): void
{
    // Set configuration defaults
    $this->configService->set('plugin_enabled', true);
    $output->writeln('<info>Plugin configuration initialized</info>');
}
```

---

### Maintenance Hooks

#### `runCronTasks(OutputInterface $output): void`

**Purpose:** Run periodic maintenance tasks.

**When Called:** By `app:cron` command (typically every 5 minutes via cron job).

**Use Case:** Cleanup, notifications, data processing, scheduled tasks.

**Example:**
```php
public function runCronTasks(OutputInterface $output): void
{
    // Close expired votes
    $expiredVotes = $this->voteRepository->findExpired();
    foreach ($expiredVotes as $vote) {
        $vote->setIsClosed(true);
    }
    $this->em->flush();

    $output->writeln(sprintf('Closed %d expired votes', count($expiredVotes)));
}
```

**Leave empty if no cron tasks:**
```php
public function runCronTasks(OutputInterface $output): void
{
    // No cron tasks for this plugin
}
```

---

### Admin Integration

#### `getAdminSystemLinks(): ?AdminSection`

**Purpose:** Add links to the admin sidebar.

**When Called:** When rendering admin pages.

**Returns:** `AdminSection` object or `null`.

**Note:** This method is deprecated. Use `AdminNavigationInterface` instead (see [Optional Interfaces](#optional-interfaces)).

**Example:**
```php
public function getAdminSystemLinks(): ?AdminSection
{
    return null; // Use AdminNavigationInterface instead
}
```

---

## Optional Interfaces

Plugins can implement additional interfaces for advanced functionality.

### AdminNavigationInterface

**Purpose:** Add navigation to the admin sidebar (modern approach).

**File:** `src/Controller/Admin/AdminNavigationInterface.php`

**When Called:** When rendering admin pages.

**Example:**
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
                    active: 'dashboard'
                ),
                new AdminLink(
                    label: 'menu_admin_settings',
                    route: 'app_admin_plugin_settings',
                    active: 'settings',
                    role: 'ROLE_ADMIN' // Optional - restrict by role
                ),
            ],
            sectionRole: 'ROLE_ORGANIZER', // Optional - hide entire section
        );
    }
}
```

**AdminLink Parameters:**
- `label` - Translation key for link text
- `route` - Symfony route name
- `active` - State identifier (for highlighting active link)
- `role` - Optional role requirement (filters this link)

**AdminNavigationConfig Parameters:**
- `section` - Section name in sidebar
- `links` - Array of AdminLink objects
- `sectionRole` - Optional role requirement (hides entire section)

---

### EventFilterInterface

**Purpose:** Filter which events are visible to users.

**File:** `src/Filter/Event/EventFilterInterface.php`

**When Called:** Every event query (lists, searches, details).

**Tag:** `#[AutoconfigureTag('app.event_filter')]`

**Example:**
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
        // Return array of event IDs that SHOULD be visible
        // Empty array = no filtering
        return $this->getVisibleEventIds();
    }

    public function isEventAccessible(int $eventId): bool
    {
        // Return true if event should be accessible
        return $this->canUserAccessEvent($eventId);
    }
}
```

**Use Cases:**
- Private/public event visibility
- Group-based event filtering
- Permission-based access control

---

### MenuFilterInterface

**Purpose:** Filter menu links based on context.

**File:** `src/Filter/Menu/MenuFilterInterface.php`

**When Called:** When rendering navigation menu.

**Tag:** `#[AutoconfigureTag('app.menu_filter')]`

**Example:**
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
        // Filter or modify menu links based on context
        return array_filter($links, function($link) {
            return $this->shouldShowLink($link);
        });
    }
}
```

**Use Cases:**
- Hide links based on domain/context
- Multi-tenant menu filtering
- Permission-based menu visibility

---

### CmsFilterInterface

**Purpose:** Filter which CMS pages are visible.

**File:** `src/Filter/Cms/CmsFilterInterface.php`

**When Called:** When querying CMS pages.

**Tag:** `#[AutoconfigureTag('app.cms_filter')]`

**Example:**
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
        // Return array of page slugs that SHOULD be visible
        // Empty array = no filtering
        return $this->getVisiblePageSlugs();
    }
}
```

---

### MemberFilterInterface

**Purpose:** Filter which members are visible in member lists.

**File:** `src/Filter/Member/MemberFilterInterface.php`

**When Called:** When rendering member lists.

**Tag:** `#[AutoconfigureTag('app.member_filter')]`

**Example:**
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
        // Return array of user IDs that SHOULD be visible
        // Empty array = no filtering
        return $this->getVisibleUserIds();
    }
}
```

---

### ActionAuthorizationInterface

**Purpose:** Control whether users can perform specific actions.

**File:** `src/Authorization/Action/ActionAuthorizationInterface.php`

**When Called:** Before RSVP, comments, uploads, etc.

**Tag:** `#[AutoconfigureTag('app.action_authorization')]`

**Example:**
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
        // - true: explicitly allow
        // - false: explicitly deny
        // - null: no opinion (let other providers decide)

        if ($action === 'event.rsvp') {
            return $this->canUserRsvp($eventId, $user);
        }

        return null; // Don't restrict other actions
    }
}
```

**Available Actions:**
- `'event.rsvp'` - Toggle RSVP on event
- `'event.comment'` - Add comment to event
- `'event.upload'` - Upload images to event

**Priority Logic:** AND logic - any `false` denies the action.

---

### ActionAuthorizationMessageProviderInterface

**Purpose:** Provide custom error messages for denied actions.

**File:** `src/Authorization/Action/ActionAuthorizationMessageProviderInterface.php`

**When Called:** When an action is denied.

**Tag:** `#[AutoconfigureTag('app.action_authorization_message')]`

**Example:**
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
        // Check if user has pending membership
        if ($this->hasPendingMembership($user, $eventId)) {
            return new UnauthorizedMessage(
                message: 'Your membership is pending approval. Please wait.',
                type: FlashMessageType::Warning
            );
        }

        return null; // Use default message
    }
}
```

**FlashMessageType Options:**
- `FlashMessageType::Success` - Green notification
- `FlashMessageType::Warning` - Orange/yellow notification
- `FlashMessageType::Error` - Red notification
- `FlashMessageType::Info` - Blue notification

---

### EventFilterFormContributorInterface

**Purpose:** Add custom fields to the event filter form.

**File:** `src/Form/EventFilterFormContributorInterface.php`

**When Called:** When building the event filter form.

**Tag:** `#[AutoconfigureTag('app.event_filter_form_contributor')]`

**Example:**
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

**Purpose:** Provide custom notifications for the notification system.

**File:** `src/Notification/NotificationProviderInterface.php`

**When Called:** When rendering the notification menu.

**Tag:** `#[AutoconfigureTag('app.notification_provider')]`

**Example:**
```php
namespace Plugin\YourPlugin\Notification;

use App\Notification\NotificationProviderInterface;
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
                icon: 'fa fa-clock'
            ),
        ];
    }
}
```

---

## Quick Start

### Minimal Plugin Structure

```
plugins/
  your-plugin/
    config/
      packages/          # Symfony package configs
      routes.yaml        # Plugin routes
      services.yaml      # Service container config
    src/
      Kernel.php         # Plugin entry point
      Entity/            # Plugin entities
      Controller/        # Controllers
      Repository/        # Repositories
      Service/           # Business logic
      DataFixtures/      # Fixture data
    templates/           # Twig templates
    CLAUDE.md            # Plugin documentation
```

### Minimal Kernel.php

```php
<?php declare(strict_types=1);

namespace Plugin\YourPlugin;

use App\Entity\AdminSection;
use App\Entity\EventListItemTag;
use App\Entity\Link;
use App\Plugin;
use Symfony\Component\Console\Output\OutputInterface;

readonly class Kernel implements Plugin
{
    public function getPluginKey(): string
    {
        return 'your-plugin';
    }

    public function getMenuLinks(): array
    {
        return [];
    }

    public function getEventTile(int $eventId): ?string
    {
        return null;
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
    }

    public function preFixtures(OutputInterface $output): void
    {
    }

    public function postFixtures(OutputInterface $output): void
    {
    }

    public function getAdminSystemLinks(): ?AdminSection
    {
        return null;
    }

    public function getFooterAbout(): ?string
    {
        return null;
    }

    public function runCronTasks(OutputInterface $output): void
    {
    }

    public function getEventListItemTags(int $eventId): array
    {
        return [];
    }

    public function getMemberPageTop(): ?string
    {
        return null;
    }
}
```

### Enable Your Plugin

```bash
# Enable and run migrations
just plugin-enable your-plugin

# Load fixtures
just devModeFixtures
```

---

## Plugin Architecture

### Namespace Convention

All plugin code must use the `Plugin\YourPluginName` namespace:

```php
namespace Plugin\YourPlugin\Controller;
namespace Plugin\YourPlugin\Entity;
namespace Plugin\YourPlugin\Service;
```

### Service Registration

Services are auto-registered if placed in `src/` directory. For manual registration, use `config/services.yaml`:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    Plugin\YourPlugin\:
        resource: '../src/'
        exclude:
            - '../src/Kernel.php'
            - '../src/Entity/'
```

### Routes

Define routes in `config/routes.yaml`:

```yaml
your_plugin:
    resource: ../src/Controller/
    type: attribute
    prefix: /your-plugin
```

### Templates

Templates go in `templates/` directory with namespace:

```twig
{# Render from plugin: @YourPlugin/page.html.twig #}
{% extends 'base.html.twig' %}

{% block body %}
    <h1>Your Plugin Page</h1>
{% endblock %}
```

### Fixtures

Extend `App\DataFixtures\AbstractFixture` for type-safe fixture references:

```php
namespace Plugin\YourPlugin\DataFixtures;

use App\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

class YourFixture extends AbstractFixture
{
    public function load(ObjectManager $manager): void
    {
        // Get core fixture references
        $user = $this->getRefUser('Admin User');
        $event = $this->getRefEvent('Tech Meetup');

        // Create your entities
        $entity = new YourEntity();
        $entity->setUser($user);
        $entity->setEvent($event);

        $manager->persist($entity);
        $manager->flush();
    }
}
```

---

## Best Practices

### 1. No Core Modifications

- Never modify core entities
- Use junction tables for relationships
- Implement interfaces for integration

### 2. Use Tagged Services

Tag services for auto-discovery:

```php
#[AutoconfigureTag('app.event_filter')]
#[AutoconfigureTag('app.action_authorization')]
```

### 3. Return Null When Not Applicable

Don't return empty strings or arrays when null is expected:

```php
public function getEventTile(int $eventId): ?string
{
    return null; // Not ""
}
```

### 4. Handle Missing Data Gracefully

```php
public function getEventTile(int $eventId): ?string
{
    $data = $this->repository->find($eventId);

    if ($data === null) {
        return null; // Don't show tile if no data
    }

    return $this->twig->render('@YourPlugin/tile.html.twig', ['data' => $data]);
}
```

### 5. Use Priority for Ordering

Higher priority runs first:

```php
public function getPriority(): int
{
    return 100; // Runs before priority 50
}
```

### 6. Document Your Plugin

Create `CLAUDE.md` in plugin root with:
- Plugin purpose
- Quick start guide
- Key features
- Architecture notes

---

## Examples from Existing Plugins

### Simple Plugin (Dishes)

- Adds menu link
- No event tiles
- No filters
- Minimal complexity

**See:** `plugins/dishes/src/Kernel.php`

### Intermediate Plugin (Film Club)

- Multiple menu links
- Event tiles (voting)
- Post-extend fixtures (votes for events)
- Cron tasks (close expired votes)

**See:** `plugins/filmclub/src/Kernel.php`

### Advanced Plugin (MultiSite)

- Event filtering (group-based)
- Menu filtering (context-aware)
- Action authorization (membership)
- Admin navigation
- Multiple interfaces

**See:** `plugins/multisite/CLAUDE.md`

---

## Troubleshooting

### Plugin Not Showing Up

1. Check `config/plugins.php` - is it enabled?
2. Run `just plugin-enable your-plugin`
3. Clear cache: `just app cache:clear`

### Services Not Autowired

1. Check namespace: `Plugin\YourPlugin\*`
2. Verify `config/services.yaml` resource path
3. Clear cache and rebuild container

### Templates Not Found

1. Use namespace: `@YourPlugin/template.html.twig`
2. Check template is in `templates/` directory
3. Clear template cache

### Fixtures Not Loading

1. Ensure fixtures extend `AbstractFixture`
2. Check fixture is in `src/DataFixtures/`
3. Run `just devModeFixtures` to reload

---

## Additional Resources

- **Core Architecture:** See `/.claude/architecture.md`
- **Core Conventions:** See `/.claude/conventions.md`
- **Testing Guide:** See `/.claude/testing.md`
- **Example Plugins:** See `plugins/dishes/`, `plugins/filmclub/`

---

**Questions?** Check existing plugin implementations or refer to core documentation.
