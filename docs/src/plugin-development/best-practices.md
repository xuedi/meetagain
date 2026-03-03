# Best Practices

Coding patterns that keep plugins safe, correct, and removable.

---

## Return `null`, not empty string

`null` signals "nothing to render" and lets the core skip the render call entirely.
An empty string renders as blank HTML, which wastes a template render and may break layout.

```php
// Correct
public function getEventTile(int $eventId): ?string
{
    return null;
}

// Wrong
public function getEventTile(int $eventId): ?string
{
    return ''; // Empty string is not the same as "nothing to show"
}
```

---

## Make services `readonly`

Declare service classes `readonly` to make dependencies explicit and prevent accidental mutation:

```php
readonly class YourService
{
    public function __construct(
        private YourRepository $repository,
        private Environment $twig,
    ) {}
}
```

Exception: if you need a per-request memo field (e.g. a cached query result), the class cannot
be `readonly`. In that case, document why.

---

## Never modify core entities

Do not add properties to core entities (`Event`, `User`, `Member`, etc.) or create Doctrine
associations from core to plugin entities. Instead, use [junction tables with INT IDs](architecture.md#use-int-ids-not-foreign-keys).

This keeps the plugin removable without leaving orphaned schema changes in core.

---

## Use `#[AutoconfigureTag]` for auto-registration

Optional interface implementations are discovered automatically when you add the tag attribute.
No manual entry in `services.yaml` is needed:

```php
#[AutoconfigureTag('app.event_filter')]
readonly class MyFilter implements EventFilterInterface
{
    // ...
}
```

If you forget the tag, the interface will be ignored silently — this is the most common cause
of filters and handlers not firing. See [Troubleshooting](troubleshooting.md).

---

## Handle missing data gracefully

Always null-check before rendering templates. Do not let a missing database row cause an error:

```php
public function getEventTile(int $eventId): ?string
{
    $data = $this->repository->find($eventId);

    if ($data === null) {
        return null; // Nothing to show — not an error
    }

    return $this->twig->render('@YourPlugin/tile.html.twig', ['data' => $data]);
}
```

---

## Use priority for ordering

When multiple plugins implement the same interface, priority controls execution order.
Higher number runs first. The default for core is typically 100.

```php
public function getPriority(): int
{
    return 100; // Runs before priority 50, after priority 200
}
```

Avoid magic values — document why you chose a specific priority if it's not the default.

---

## No foreign key constraints

Plugin database tables must not add foreign key constraints to core tables.
Core tables must be droppable without affecting plugins, and plugins must be removable
without leaving broken FK references in core.

Use `INT` columns and resolve references in application code, not at the database layer.

---

## Document your plugin

Every plugin should have a `README.md` at its root. Minimum content:

- **Purpose** — what problem this plugin solves
- **Interfaces implemented** — which optional hooks are used
- **Configuration** — any settings or environment variables required
- **Quick start** — how to enable and load fixtures

This is especially important for plugins that are published or shared with other developers.
