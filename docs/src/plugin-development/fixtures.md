# Data Fixtures

Fixtures provide deterministic development and test data. Plugin fixtures integrate with
the core fixture system through three lifecycle hooks and a shared base class.

---

## Why extend AbstractFixture?

`App\DataFixtures\AbstractFixture` provides type-safe helper methods that resolve references
to core fixture objects by name. This avoids querying the database and ensures your fixture
data links to the same objects the core fixtures created.

```php
namespace Plugin\YourPlugin\DataFixtures;

use App\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

class YourFixture extends AbstractFixture
{
    public function load(ObjectManager $manager): void
    {
        // Resolve core fixture references by name
        $user  = $this->getRefUser('Admin User');
        $event = $this->getRefEvent('Tech Meetup');

        $entity = new YourEntity();
        $entity->setUserId($user->getId());
        $entity->setEventId($event->getId());

        $manager->persist($entity);
        $manager->flush();
    }
}
```

---

## Cross-fixture references

`AbstractFixture` provides typed getters for all core reference types:

| Method | Returns |
|---|---|
| `getRefUser(string $name)` | `User` |
| `getRefEvent(string $name)` | `Event` |
| `getRefLocation(string $name)` | `Location` |
| `getRefCms(string $name)` | `CmsPage` |

Reference names are defined in the core fixture classes. To see available names, check
`src/DataFixtures/` in the core application.

---

## Fixture groups

Fixtures are tagged with groups to control when they run:

| Group | When used |
|---|---|
| `plugin` | Loaded by `just devModeFixtures` — full development reset |
| `install` | Loaded during first-time installation (minimal required data) |

Tag your fixture class with the appropriate group:

```php
use Doctrine\Bundle\FixturesBundle\Attribute\AsFixture;

#[AsFixture(groups: ['plugin'])]
class YourFixture extends AbstractFixture
{
    // ...
}
```

Most plugin fixtures belong to the `plugin` group. Only add `install` if the data is
required for the application to function at all (e.g. default configuration values).

---

## The three fixture hooks

These methods in `Kernel.php` are called by core fixture commands in a specific order.

### Timing diagram

```
just devModeFixtures
  │
  ├─ doctrine:fixtures:load       (core + plugin fixtures from src/DataFixtures/)
  │
  ├─ app:plugin:pre-fixtures      → preFixtures() on each Kernel
  │
  ├─ app:event:add-fixture        (extends events with recurring instances)
  │    └─ app:plugin:post-extend  → loadPostExtendFixtures() on each Kernel
  │
  └─ app:plugin:post-fixtures     → postFixtures() on each Kernel
```

---

### `preFixtures(OutputInterface $output): void`

**Called:** After Doctrine fixture load, before recurring event extension.

**Use for:**
- Data migrations (check if old schema columns exist, transform data)
- Schema preparation
- Outputting progress messages

```php
public function preFixtures(OutputInterface $output): void
{
    $output->writeln('<info>Checking for pending migrations...</info>');

    if ($this->needsMigration()) {
        $this->runMigration();
        $output->writeln('<info>Migration complete</info>');
    }
}
```

---

### `loadPostExtendFixtures(OutputInterface $output): void`

**Called:** After recurring event instances have been created.

**Use for:**
- Creating data that is tied to specific future event occurrences
- Votes, RSVPs, or bookings that reference recurring instances

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
    $output->writeln(sprintf('<info>Created %d votes</info>', count($pastEvents)));
}
```

---

### `postFixtures(OutputInterface $output): void`

**Called:** After all plugin fixture hooks have run.

**Use for:**
- Setting configuration defaults
- Post-processing or cleanup of fixture data

```php
public function postFixtures(OutputInterface $output): void
{
    $this->configService->set('your_plugin.enabled', true);
    $this->configService->set('your_plugin.default_limit', 10);
    $output->writeln('<info>Configuration defaults set</info>');
}
```

---

## Dev commands

```bash
# Full dev reset — loads all fixtures including plugin group
just devModeFixtures

# Load only a specific plugin's fixtures
just devModeFixtures your-plugin

# Minimal reset — loads install group only (faster)
just devModeMinimal
```
