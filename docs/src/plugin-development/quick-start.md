# Quick Start

Get a working plugin skeleton running in about 5 minutes.

---

## Directory structure

```
plugins/
  your-plugin/
    config/
      packages/          # Symfony package configs (optional)
      routes.yaml        # Plugin routes
      services.yaml      # Service container config
    src/
      Kernel.php         # Plugin entry point (required)
      Controller/        # Symfony controllers
      Entity/            # Doctrine entities
      Repository/        # Repositories
      Service/           # Business logic
      DataFixtures/      # Fixture classes
    templates/           # Twig templates
    README.md            # Plugin documentation
```

---

## Create a minimal Kernel.php

Every plugin must have a `Kernel.php` at `plugins/your-plugin/src/Kernel.php`
that implements the `App\Plugin` interface.

```php
<?php declare(strict_types=1);

namespace Plugin\YourPlugin;

use App\Entity\AdminSection;use App\Plugin;use Symfony\Component\Console\Output\OutputInterface;

readonly class Kernel implements Plugin
{
    public function getPluginKey(): string
    {
        return 'your-plugin'; // Must match the directory name
    }

    public function getMenuLinks(): array
    {
        return [];
    }

    public function getEventTile(int $eventId): ?string
    {
        return null;
    }

    public function getEventListItemTags(int $eventId): array
    {
        return [];
    }

    public function getMemberPageTop(): ?string
    {
        return null;
    }

    public function getFooterAbout(): ?string
    {
        return null;
    }

    public function preFixtures(OutputInterface $output): void
    {
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
    }

    public function postFixtures(OutputInterface $output): void
    {
    }

    public function runCronTasks(OutputInterface $output): void
    {
    }

    public function getAdminSystemLinks(): ?AdminSection
    {
        return null; // Deprecated — use AdminNavigationInterface instead
    }
}
```

---

## Step-by-step

1. **Create the directories:**
   ```bash
   mkdir -p plugins/your-plugin/{config,src,templates}
   ```

2. **Write `Kernel.php`** using the template above, replacing `YourPlugin` with your plugin name
   (PascalCase) and `your-plugin` with its slug (kebab-case).

3. **Create `config/services.yaml`:**
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

4. **Create `config/routes.yaml`:**
   ```yaml
   your_plugin:
       resource: ../src/Controller/
       type: attribute
       prefix: /your-plugin
   ```

5. **Enable the plugin and load fixtures:**
   ```bash
   just plugin-enable your-plugin
   just devModeFixtures
   ```

6. **Verify:** Open the app in your browser — your plugin should be active.

---

## Next steps

- [Required Hooks](required-hooks.md) — understand what each method does and when to use it
- [Optional Hooks](optional-hooks.md) — add admin navigation, filters, or authorization
- [Architecture](architecture.md) — learn the structural conventions before adding controllers
