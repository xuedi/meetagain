# Required Hooks

Every plugin must implement `App\Plugin`. This page is the full reference for each method.

---

## Method reference

| Method                     | Return type               | When called                     | Required?        |
|----------------------------|---------------------------|---------------------------------|------------------|
| `getPluginKey()`           | `string`                  | Plugin registration             | Yes              |
| `getMenuLinks()`           | `array<Link>`             | Every page load (nav rendering) | Yes              |
| `getEventTile()`           | `?string`                 | Event detail page render        | Yes              |
| `getEventListItemTags()`   | `array<EventListItemTag>` | Event list rendering            | Yes              |
| `getMemberPageTop()`       | `?string`                 | Admin member list render        | Yes              |
| `getFooterAbout()`         | `?string`                 | Every page load (footer)        | Yes              |
| `getAdminSystemLinks()`    | `?AdminSection`           | Admin page render               | Yes (deprecated) |

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

| Priority range | Position                   |
|----------------|----------------------------|
| 0–99           | Before Events              |
| 100            | Events (core)              |
| 101–199        | Between Events and Members |
| 200            | Members (core)             |
| 201–249        | Between Members and Groups |
| 250            | Groups (core)              |
| 251–299        | Between Groups and Admin   |
| 300+           | Admin (core) and after     |

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

### `getEventTile(int $eventId, EventTileLocation $location): ?string`

**Purpose:** Render a custom tile/box on the event detail page.

**When called:** Once per location when rendering `/event/{id}`.

**Locations:** `EventTileLocation::Sidebar` and `EventTileLocation::BottomSidebar` render in the
narrow right column; `EventTileLocation::Center` renders in the wide column between the event
description and the RSVP box, and is only rendered for logged-in visitors.

**Returns:** Rendered HTML string, or `null` if this plugin has nothing to show for that location.

```php
public function getEventTile(int $eventId, EventTileLocation $location): ?string
{
    if ($location !== EventTileLocation::Center) {
        return null;
    }

    $vote = $this->voteRepository->findByEventId($eventId);

    if ($vote === null) {
        return null;
    }

    return $this->twig->render('@Example/tile/event.html.twig', [
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

## Demo data

The `Plugin` interface has no data-loading hooks. Development data comes from the demo archives (see
[Demo Data](../core-development/demo-data.md)); make your plugin's data travel in them through the
export and import seams in [Optional Hooks](optional-hooks.md#export-and-import).

---

## Periodic and background work

Neither is a `Plugin` method. Both are classes the container finds on its own, and both stop while an admin has the
plugin switched off.

### A periodic task implements `TaskInterface`

```php
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;

readonly class CloseExpiredVotesTask implements TaskInterface
{
    public function __construct(
        private VoteRepository $votes,
    ) {}

    public function getIdentifier(): string
    {
        return 'your-plugin.close-expired-votes';
    }

    public function getCadence(): Cadence
    {
        return new Cadence('*/15 * * * *');
    }

    public function getDescription(): string
    {
        return 'Closes every vote whose deadline has passed, every fifteen minutes.';
    }

    public function run(): Result
    {
        $closed = $this->votes->closeExpired();

        return new Result(Status::ok, sprintf('%d votes closed', $closed));
    }
}
```

- The identifier is stable: the run log and the admin's Tasks page are keyed by it.
- The description is one plain English paragraph saying what the task does and when; the task's detail page shows it.
- The cron expression carries the timing, a time window included (`0 7-21 * * *`); the task does not check the clock.
- `new Cadence('30 3 * * *', jitterSeconds: 1800, offloaded: true)` spreads a nightly task over half an hour and runs
  it on a message worker instead of in the scheduler. Offload anything that takes more than a few seconds.
- Every run is recorded with its `Result`. A thrown exception is recorded as `exception` and logged.
- `php bin/console app:task:run your-plugin.close-expired-votes` runs it once, now.

### A maintenance action implements `ActionInterface`

Work an admin starts by hand - an import, a recalculation, a reset to defaults - is an action, not a task. It shows
up on Admin, System, Maintenance with a form built from its parameters, and runs on a message worker once per
submission.

```php
use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionParameter;
use Module\Task\Contract\ActionSection;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;

readonly class RecountVotesAction implements ActionInterface
{
    public function __construct(
        private VoteRepository $votes,
    ) {}

    public function getIdentifier(): string
    {
        return 'your-plugin.recount-votes';
    }

    public function getSection(): ActionSection
    {
        return ActionSection::Recalculate;
    }

    public function getLabel(): string
    {
        return 'your_plugin_admin.recount_label';
    }

    public function getDescription(): string
    {
        return 'your_plugin_admin.recount_description';
    }

    public function getParameters(): array
    {
        return [
            ActionParameter::flag('closed_only', 'your_plugin_admin.recount_closed_only', default: true, remembered: true),
        ];
    }

    public function getFacts(): array
    {
        return ['your_plugin_admin.fact_open_votes' => (string) $this->votes->countOpen()];
    }

    public function run(array $values): Result
    {
        $count = $this->votes->recount((bool) $values['closed_only']);

        return new Result(Status::ok, sprintf('%d votes recounted', $count));
    }
}
```

- Labels, descriptions and fact labels are translation keys; the admin page translates them.
- Only one request per action runs at a time; a second one waits its turn.
- A run the worker did not finish is marked failed and is not repeated - queue it again from the page. Write
  `run()` so that a second run after an interrupted one does no harm.
- A parameter built with `remembered: true` gets a settings form on the action's page. Its saved value pre-fills the
  run form, and `Module\Task\Contract\ActionSettingsInterface::get($identifier, $key)` reads it from anywhere else,
  for example from a task that repeats the work on a schedule.
- Every run lands in the run log beside the tasks.

### Work a request hands off is a message

A request that must not wait - sending, calling a slow API - writes its work to a table and dispatches a small message
to the `async` transport; a worker handles it within about a second.

```php
#[AsMessageHandler]
readonly class ProcessUploadHandler
{
    public function __invoke(ProcessUpload $message): void
    {
        // claim the rows the message stands for and work them
    }
}
```

- The table row is the record and the message is only a doorbell: it carries an id at most. Add a periodic task that
  works whatever a lost message left behind.
- Route the message class to `async` in `plugins/<name>/config/packages/messenger.yaml`.
- A handler runs in a long-lived process. A service that keeps rows, entities or the current user in a property
  implements `Symfony\Contracts\Service\ResetInterface` and clears them in `reset()`, which runs between messages.
- `Module\Task\Contract\TriggerInterface::runSoon('<identifier>')` sends one run of a periodic task to a worker now,
  for when a change should not wait for the next scheduled run.

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
