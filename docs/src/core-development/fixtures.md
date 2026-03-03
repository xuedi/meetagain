# Data Fixtures

Fixtures provide deterministic development and test data. The core application ships a custom
`AbstractFixture` base class that all fixture classes — both core and plugin — extend.

---

## Core fixtures overview

The following fixture classes live in `src/DataFixtures/` and are loaded on every full dev reset:

| Fixture class | What it creates | Reference names |
|---|---|---|
| `SystemUserFixture` | System/bot user for automated actions | `system` |
| `UserFixture` | Regular users (admin, organizers, members) | `Admin`, `Crystal Liu`, `Adem Lane`, … |
| `HostFixture` | Host records linked to users | `Admin`, `Crystal`, `Adem`, `Jessie`, `Mollie` |
| `LocationFixture` | Physical and online venues | `Weiqi Cafe`, `Community Center`, `Online Platform`, … |
| `EventFixture` | Events with translations, RSVPs, comments | `Weekly Go Study Group`, `Berlin Go Tournament 2026`, … |
| `CmsFixture` | CMS pages (about, imprint, privacy, …) | `about`, `imprint`, … |
| `CmsBlockFixture` | Content blocks for CMS pages | — |
| `ConfigFixture` | Application configuration defaults | — |
| `LanguageFixture` | Supported languages | — |
| `EmailTemplateFixture` | Email template content | — |
| `ActivityFixture` | Activity log entries | — |
| `MinimalAdminFixture` | Minimal admin user for install group | — |

Reference names are the string keys passed to `addRefXxx()` in each fixture. Check the
fixture class constants (e.g. `UserFixture::ADMIN`) for the exact values.

---

## Why extend AbstractFixture?

`App\DataFixtures\AbstractFixture` provides type-safe helper methods that resolve references
to core fixture objects by name. This avoids querying the database and ensures your fixture
data links to the same objects the core fixtures created.

```php
namespace App\DataFixtures;

use Doctrine\Persistence\ObjectManager;

class MyEntityFixture extends AbstractFixture
{
    public function load(ObjectManager $manager): void
    {
        // Resolve core fixture references by name
        $user  = $this->getRefUser(UserFixture::ADMIN);
        $event = $this->getRefEvent(EventFixture::WEEKLY_GO_STUDY);

        $entity = new MyEntity();
        $entity->setUser($user);
        $entity->setEvent($event);

        $manager->persist($entity);
        $this->addRefMyEntity('my_entity', $entity);

        $manager->flush();
    }
}
```

---

## Cross-fixture references

`AbstractFixture` provides typed getters and setters for all core reference types:

| Getter | Setter | Returns |
|---|---|---|
| `getRefUser(string $name)` | `addRefUser(string $name, User $entity)` | `User` |
| `getRefEvent(string $name)` | `addRefEvent(string $name, Event $entity)` | `Event` |
| `getRefLocation(string $name)` | `addRefLocation(string $name, Location $entity)` | `Location` |
| `getRefCms(string $name)` | `addRefCms(string $name, Cms $entity)` | `Cms` |
| `getRefHost(string $name)` | `addRefHost(string $name, Host $entity)` | `Host` |

References are keyed by the string name you pass. For core fixtures, use the class constants
(e.g. `UserFixture::ADMIN`, `EventFixture::WEEKLY_GO_STUDY`) to avoid typos.

---

## Creating a core fixture

Full example of a new `AbstractFixture` subclass for a new entity:

```php
<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Rating;
use Doctrine\Bundle\FixturesBundle\Attribute\AsFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

#[AsFixture(groups: ['base'])]
class RatingFixture extends AbstractFixture implements DependentFixtureInterface
{
    public const string FIVE_STARS = 'five-star-rating';

    public function load(ObjectManager $manager): void
    {
        $this->start();

        $rating = new Rating();
        $rating->setUser($this->getRefUser(UserFixture::ADMIN));
        $rating->setEvent($this->getRefEvent(EventFixture::WEEKLY_GO_STUDY));
        $rating->setScore(5);

        $manager->persist($rating);
        $this->addRefRating(self::FIVE_STARS, $rating);

        $manager->flush();
        $this->stop();
    }

    public function getDependencies(): array
    {
        return [UserFixture::class, EventFixture::class];
    }
}
```

Key points:
- Use `#[AsFixture(groups: ['base'])]` for standard dev data
- Use `#[AsFixture(groups: ['install'])]` only for data required at first-time install
- Declare `getDependencies()` to ensure load order
- Call `$this->start()` / `$this->stop()` for consistent progress output

---

## Fixture groups

Fixtures are tagged with groups to control when they run:

| Group | When used |
|---|---|
| `base` | Loaded by `just devModeFixtures` — full development reset (default for core fixtures) |
| `plugin` | Plugin fixtures loaded during full dev reset |
| `install` | Loaded during first-time installation (minimal required data only) |

Most new fixtures belong to the `base` (core) or `plugin` (plugin code) group. Only add
`install` if the data is required for the application to function at all (e.g. default
configuration values, language records).

---

## Helper methods

```php
$this->start();                        // Prints "Creating FixtureName ..."
$this->stop();                         // Prints " OK\n"
$text = $this->getText('filename');    // Reads DataFixtures/FixtureName/filename.txt
```

Use `getText()` to keep long text content (event descriptions, CMS page bodies) in separate
`.txt` files instead of PHP strings.

---

## Dev commands

```bash
# Full dev reset — loads all fixtures
just devModeFixtures

# Load only a specific plugin's fixtures
just devModeFixtures your-plugin

# Minimal reset — loads install group only (faster)
just devModeMinimal
```

---

!!! note "Fixture hooks for plugins"
    Plugins can run code before and after fixture loading via `preFixtures()`,
    `loadPostExtendFixtures()`, and `postFixtures()` in their `Kernel.php`.
    These hooks are documented in [Required Hooks](../plugin-development/required-hooks.md).
