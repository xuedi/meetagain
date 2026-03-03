# Required Hooks

Every plugin must implement `App\Plugin`. This page is the full reference for each method.

---

## Method reference

| Method | Return type | When called | Required? |
|---|---|---|---|
| `getPluginKey()` | `string` | Plugin registration | Yes |
| `getMenuLinks()` | `array<Link>` | Every page load (nav rendering) | Yes |
| `getEventTile()` | `?string` | Event detail page render | Yes |
| `getEventListItemTags()` | `array<EventListItemTag>` | Event list rendering | Yes |
| `getMemberPageTop()` | `?string` | Admin member list render | Yes |
| `getFooterAbout()` | `?string` | Every page load (footer) | Yes |
| `preFixtures()` | `void` | Before fixture loading | Yes |
| `loadPostExtendFixtures()` | `void` | After event recurring extension | Yes |
| `postFixtures()` | `void` | After all fixtures loaded | Yes |
| `runCronTasks()` | `void` | Every cron run (~5 min) | Yes |
| `getAdminSystemLinks()` | `?AdminSection` | Admin page render | Yes (deprecated) |

---

## Content hooks

### `getPluginKey(): string`

**Purpose:** Returns the unique identifier for this plugin.

**When called:** During plugin registration and service container building.

**Rules:** Must match the plugin's directory name (kebab-case).

```php
public function getPluginKey(): string
{
    return 'dishes'; // Must match plugins/dishes/
}
```

---

### `getMenuLinks(): array`

**Purpose:** Add links to the main navigation bar.

**When called:** Every page load when rendering the nav.

**Returns:** `array<Link>` — empty array means no links.

**Priority ordering:**

| Priority range | Position |
|---|---|
| 0–99 | Before Events |
| 100 | Events (core) |
| 101–199 | Between Events and Members |
| 200 | Members (core) |
| 201–249 | Between Members and Groups |
| 250 | Groups (core) |
| 251–299 | Between Groups and Admin |
| 300+ | Admin (core) and after |

**Example — single link, no priority:**
```php
public function getMenuLinks(): array
{
    return [
        new Link(
            slug: $this->urlGenerator->generate('app_plugin_dishes'),
            name: 'dishes',
        ),
    ];
}
```

**Example — multiple links with explicit priority:**
```php
public function getMenuLinks(): array
{
    return [
        new Link(
            slug: $this->urlGenerator->generate('app_filmclub_filmlist'),
            name: 'films',
            priority: 120,
        ),
        new Link(
            slug: $this->urlGenerator->generate('app_filmclub_vote'),
            name: 'vote',
            priority: 130,
        ),
    ];
}
```

**No links:**
```php
public function getMenuLinks(): array
{
    return [];
}
```

---

### `getEventTile(int $eventId): ?string`

**Purpose:** Render a custom tile/box on the event detail page.

**When called:** When rendering `/event/{id}`.

**Returns:** Rendered HTML string, or `null` if this plugin has nothing to show.

```php
public function getEventTile(int $eventId): ?string
{
    $vote = $this->voteRepository->findByEventId($eventId);

    if ($vote === null) {
        return null;
    }

    return $this->twig->render('@Filmclub/tile/event.html.twig', [
        'vote' => $vote,
        'eventId' => $eventId,
    ]);
}
```

---

### `getEventListItemTags(int $eventId): array`

**Purpose:** Add badges to events in list views (homepage, search results, etc.).

**When called:** When rendering event lists.

**Returns:** `array<EventListItemTag>` — empty array means no badges for this event.

**Tag properties:**
- `text` — Display label
- `color` — Bulma color class: `is-info`, `is-success`, `is-warning`, `is-danger`
- `icon` — FontAwesome icon class
- `url` — Optional link (`null` for non-clickable tags)

```php
public function getEventListItemTags(int $eventId): array
{
    $dish = $this->dishRepository->findByEventId($eventId);

    if ($dish === null || !$dish->isVegetarian()) {
        return [];
    }

    return [
        new EventListItemTag(
            text: 'Vegetarian',
            color: 'is-success',
            icon: 'fa fa-leaf',
            url: null,
        ),
    ];
}
```

---

### `getMemberPageTop(): ?string`

**Purpose:** Inject content above the admin member list.

**When called:** When rendering `/admin/members`.

**Returns:** Rendered HTML string, or `null`.

**Use case:** Pending approvals notices, membership stats, quick actions.

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

### `getFooterAbout(): ?string`

**Purpose:** Add content to the footer's "About" section.

**When called:** Every page load when rendering the footer.

**Returns:** Rendered HTML string, or `null`.

```php
public function getFooterAbout(): ?string
{
    return $this->twig->render('@YourPlugin/footer/about.html.twig');
}
```

---

## Fixture hooks

See [Data Fixtures](../core-development/fixtures.md) for a full guide on timing, groups, and cross-fixture references.

### `preFixtures(OutputInterface $output): void`

**Purpose:** Run tasks **before** plugin fixtures are loaded.

**When called:** By `app:plugin:pre-fixtures` command, after base fixtures load.

**Use case:** Data migration, schema preparation.

```php
public function preFixtures(OutputInterface $output): void
{
    $output->writeln('Running migration...');
    // Run your migration logic here
}
```

Leave empty if not needed:
```php
public function preFixtures(OutputInterface $output): void
{
    // No pre-fixture tasks
}
```

---

### `loadPostExtendFixtures(OutputInterface $output): void`

**Purpose:** Create fixture data that depends on recurring event instances.

**When called:** By `app:event:add-fixture` command, after events have been extended.

**Use case:** Votes, RSVPs, or other data tied to future event occurrences.

```php
public function loadPostExtendFixtures(OutputInterface $output): void
{
    $pastEvents = $this->eventRepository->getPastEvents(10);

    foreach ($pastEvents as $event) {
        $vote = new Vote();
        $vote->setEventId($event->getId());
        $vote->setClosesAt(new DateTimeImmutable(
            $event->getStart()->format('Y-m-d H:i:s') . ' -1 day'
        ));
        $this->em->persist($vote);
    }

    $this->em->flush();
    $output->writeln('<info>Created votes for past events</info>');
}
```

---

### `postFixtures(OutputInterface $output): void`

**Purpose:** Run tasks **after** all fixtures are loaded.

**When called:** By `app:plugin:post-fixtures` command, after `doctrine:fixtures:load`.

**Use case:** Set configuration defaults, post-processing, cleanup.

```php
public function postFixtures(OutputInterface $output): void
{
    $this->configService->set('plugin_enabled', true);
    $output->writeln('<info>Plugin configuration initialized</info>');
}
```

---

## Maintenance hook

### `runCronTasks(OutputInterface $output): void`

**Purpose:** Run periodic background tasks.

**When called:** By `app:cron` command (typically every 5 minutes via cron job).

**Use case:** Cleanup, notifications, data processing, scheduled operations.

```php
public function runCronTasks(OutputInterface $output): void
{
    $expiredVotes = $this->voteRepository->findExpired();

    foreach ($expiredVotes as $vote) {
        $vote->setIsClosed(true);
    }
    $this->em->flush();

    $output->writeln(sprintf('Closed %d expired votes', count($expiredVotes)));
}
```

Leave empty if not needed:
```php
public function runCronTasks(OutputInterface $output): void
{
    // No cron tasks
}
```

---

## Deprecated hook

### `getAdminSystemLinks(): ?AdminSection`

**Purpose:** Was the original way to add admin sidebar links.

**Status:** Deprecated. Implement [AdminNavigationInterface](optional-hooks.md#adminnavigationinterface) instead.

```php
public function getAdminSystemLinks(): ?AdminSection
{
    return null; // Always return null — use AdminNavigationInterface
}
```
