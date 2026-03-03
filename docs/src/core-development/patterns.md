# Patterns

Recurring code patterns in the MeetAgain codebase. Each section shows the canonical form
with inline annotations.

---

## Service

Services are `readonly` classes with constructor injection. They contain all business logic
and have a single, focused responsibility.

```php
<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Image;
use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class CleanupService          // ← always readonly
{
    public function __construct(
        private ImageRepository $imageRepo,        // ← inject repositories
        private EntityManagerInterface $em,        // ← inject EM when writing
    ) {}

    public function removeOrphanedImages(): int
    {
        $orphaned = $this->imageRepo->findOrphaned();   // ← delegate queries to repo
        foreach ($orphaned as $image) {
            $this->em->remove($image);
        }
        $this->em->flush();                             // ← one flush per operation

        return count($orphaned);
    }
}
```

**Rules:**
- All services MUST be `readonly` (exception: services that hold a per-request memo field)
- Constructor injection only — no setter injection
- No static methods
- Single Responsibility Principle — one focused purpose per service

---

## Repository

Repositories extend `ServiceEntityRepository` and expose intent-revealing methods.
Raw `findBy()` calls belong in the repository, not in controllers or services.

```php
<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Event;
use DateTimeInterface;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Intent-revealing name → caller knows what they'll get.
     */
    public function findUpcomingEventsWithinRange(
        ?DateTimeInterface $start = null,
        ?DateTimeInterface $end = null,
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.location', 'l')
            ->addSelect('l')                          // ← eager-load to avoid N+1
            ->where('e.start >= :now')
            ->andWhere('e.canceled = false')
            ->setParameter('now', $start ?? new DateTimeImmutable())
            ->orderBy('e.start', 'ASC');

        if ($end !== null) {
            $qb->andWhere('e.start <= :end')
               ->setParameter('end', $end);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Return early on empty input — avoids a "WHERE id IN ()" query.
     *
     * @param int[] $ids
     * @return Event[]
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
```

**Rules:**
- Use `createQueryBuilder()`, never raw SQL
- Eager-load related entities with `leftJoin` + `addSelect` when they are always needed
- Return early for empty arrays to avoid malformed `IN ()` queries
- Name methods after *what* the caller wants, not *how* the query works

---

## Entity

Entities are plain Doctrine-mapped objects. They hold data and relationships — no logic.

```php
<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\EventRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
#[ORM\Index(columns: ['start', 'canceled'], name: 'event_start_idx')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(enumType: EventTypes::class)]   // ← enum column
    private EventTypes $type;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $start;

    /** @var Collection<int, User> */             // ← docblock for PHPStan
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'rsvpEvents')]
    private Collection $rsvps;

    public function __construct()
    {
        $this->rsvps = new ArrayCollection();     // ← init collections in constructor
    }

    // getters and setters below…
}
```

**Rules:**
- Use Doctrine attributes (not legacy annotations)
- Use backed enums for status/type fields with `enumType`
- Use `DateTimeImmutable` for timestamps
- Always add `@var Collection<int, EntityClass>` docblock on collections
- Initialize collections in the constructor
- No business logic — no `calculate*`, no `process*` methods

---

## Migrations

After changing an entity, generate a migration:

```bash
just app doctrine:migrations:diff
```

When **plugins are active** (multiple migration namespaces registered), Doctrine prompts
interactively. Select **`AppMigrations`** for core changes:

```
Which migrations configuration would you like to use?
 [0] AppMigrations
 [1] PluginDishesMigrations
 [2] PluginFilmclubMigrations
> 0
```

The migration file is placed in `migrations/VersionXXX.php`.

**Always review the generated SQL** before committing — Doctrine diffs occasionally include
spurious changes (enum byte-length, collation mismatches).

Run pending migrations:

```bash
just appMigrate
```

!!! note
    For plugin entity changes, generate a migration into the plugin's own namespace.
    See [Plugin Development → Architecture](../plugin-development/architecture.md#migrations).

---

## Form type

Form types extend `AbstractType` and define field configuration and constraints.

```php
<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Event;
use App\Entity\EventTypes;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 3, 'max' => 255]),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => EventTypes::class,
            ])
            ->add('start', DateTimeType::class, [
                'widget' => 'single_text',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\GreaterThan('now'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
```

---

## Command

Commands use `#[AsCommand]` and follow the same thin-delegation pattern as controllers:
receive input, call a service, report progress.

```php
<?php declare(strict_types=1);

namespace App\Command;

use App\Service\EventService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:event:extend',
    description: 'Generate future instances of recurring events',
)]
class EventExtentCommand extends Command
{
    public function __construct(
        private readonly EventService $eventService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Days ahead', 90);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        $output->writeln(sprintf('<info>Extending events %d days ahead...</info>', $days));

        $count = $this->eventService->extendRecurring($days);

        $output->writeln(sprintf('<info>Created %d new instances.</info>', $count));

        return Command::SUCCESS;
    }
}
```

For scheduled (cron-like) commands, implement `CronTaskInterface` so the Symfony Scheduler
picks them up automatically.

---

## Event Subscriber

Use `#[AsEventListener]` to react to Symfony framework events without coupling the listener
to the code that fires the event.

```php
<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\LocaleService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
readonly class LoginSubscriber
{
    public function __construct(
        private LocaleService $localeService,
    ) {}

    public function __invoke(LoginSuccessEvent $event): void
    {
        $response = $event->getResponse();
        if ($response === null) {
            return; // stateless Bearer token request — skip
        }

        $this->localeService->setLocaleCookie($event->getAuthenticatedToken()->getUser(), $response);
    }
}
```

**When to use a subscriber vs a plain service call:**
- Use a subscriber when the *trigger* and the *reaction* should be decoupled — e.g. login
  triggers a cookie set, but the login code should not know about cookies.
- Use a plain service call when the action is always required and the coupling is intentional
  — e.g. `EventService::create()` always calls `ActivityService::log()`.
