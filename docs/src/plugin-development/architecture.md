# Architecture

Structural conventions for plugin code — namespaces, services, routes, templates, and database.

---

## Namespace convention

All plugin code lives under the `Plugin\YourPluginName` namespace:

```php
namespace Plugin\YourPlugin;              // Kernel.php
namespace Plugin\YourPlugin\Controller;  // Controllers
namespace Plugin\YourPlugin\Entity;      // Doctrine entities
namespace Plugin\YourPlugin\Repository;  // Repositories
namespace Plugin\YourPlugin\Service;     // Business logic
namespace Plugin\YourPlugin\Filter;      // Filter implementations
```

The namespace root must match the plugin directory name in PascalCase:
`plugins/filmclub/` → `Plugin\Filmclub\`

---

## Service registration

Services in `src/` are auto-registered via `config/services.yaml`:

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

`Kernel.php` and entities are excluded because they are not services.
Everything else — controllers, repositories, services, filters — is auto-wired.

---

## Routes

Define all plugin routes in `config/routes.yaml`:

```yaml
your_plugin:
    resource: ../src/Controller/
    type: attribute
    prefix: /your-plugin
```

Routes are defined using PHP attributes in the controller classes:

```php
use Symfony\Component\Routing\Attribute\Route;

class ListController extends AbstractController
{
    #[Route('/your-plugin/list', name: 'app_plugin_yourplugin_list')]
    public function list(): Response
    {
        // ...
    }
}
```

---

## Templates

Templates live in `templates/` and are referenced using the Twig namespace `@YourPlugin`:

```
plugins/your-plugin/templates/
  page/
    list.html.twig
    detail.html.twig
  tile/
    event.html.twig
  admin/
    dashboard.html.twig
```

Always extend the base template:

```twig
{# templates/page/list.html.twig #}
{% extends 'base.html.twig' %}

{% block body %}
    <h1>Your Plugin Page</h1>
{% endblock %}
```

Reference from PHP code:

```php
$this->twig->render('@YourPlugin/tile/event.html.twig', ['data' => $data]);
```

---

## Database entities

### Use INT IDs, not foreign keys

Plugin entities reference core entities by ID (integer), never by Doctrine association:

```php
// Correct — ID reference, no FK constraint
#[ORM\Column(type: Types::INTEGER)]
private int $eventId;

// Wrong — Doctrine association creates a FK constraint
#[ORM\ManyToOne(targetEntity: Event::class)]
private Event $event;
```

This keeps plugins removable: dropping the plugin's tables does not break core data.

### Use junction tables for relationships

If your plugin needs to associate its data with core entities, use a separate junction table:

```php
#[ORM\Entity]
#[ORM\Table(name: 'plugin_yourplugin_event_link')]
class EventLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::INTEGER)]
    private int $eventId; // Reference, not association

    // Plugin-specific data
    #[ORM\Column(type: Types::STRING)]
    private string $someData;
}
```

### Migrations

Plugin migration namespaces are registered in `plugins/your-plugin/config/packages/doctrine.yaml`:

```yaml
doctrine_migrations:
    migrations_paths:
        PluginYourPluginMigrations: "%kernel.project_dir%/plugins/your-plugin/migrations"
```

After changing a plugin entity, run from the project root:

```bash
just app doctrine:migrations:diff
```

When prompted, select your **plugin's namespace** (not `AppMigrations`):

```
Which migrations configuration would you like to use?
 [0] AppMigrations
 [1] PluginYourPluginMigrations
> 1
```

The migration file is placed in `plugins/your-plugin/migrations/VersionXXX.php`.

!!! warning
    Never put plugin DDL in `AppMigrations` and never put core DDL in a plugin namespace.
    Wrong selection = migrations written to the wrong directory.

`just appMigrate` applies all pending migrations (core + all active plugins) in timestamp order.

---

## Plugin discovery

Core discovers plugins by scanning `plugins/*/src/Kernel.php`. Each `Kernel.php` is instantiated
and registered as a service. The plugin key returned by `getPluginKey()` is used as the identifier
throughout the system.

Plugins are enabled/disabled via `config/plugins.php` in the core application.
Use `just plugin-enable your-plugin` to add a plugin to that file.
