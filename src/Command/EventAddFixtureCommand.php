<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Comment;
use App\Plugin;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(
    name: 'app:event:add-fixture',
    description: 'Add random RSVPs, comments and plugin fixtures to extended recurring events'
)]
class EventAddFixtureCommand extends Command
{
    private const array SAMPLE_COMMENTS = [
        'Looking forward to this event!',
        'Count me in!',
        'Great event as always',
        'Can\'t wait to see everyone',
        'This is going to be awesome',
        'See you there!',
        'Excited for this one',
        'Thanks for organizing!',
        'Perfect timing',
        'I\'ll bring some snacks',
        'Who else is coming?',
        'My first time attending, looking forward to it!',
        'Hope the weather will be good',
        'I might be a bit late',
        'Bringing a friend along',
    ];

    /**
     * @param iterable<Plugin> $plugins
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepo,
        #[AutowireIterator(Plugin::class)]
        private readonly iterable $plugins,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption(
            'min-rsvps',
            null,
            InputOption::VALUE_REQUIRED,
            'Minimum number of RSVPs per event',
            '2'
        );
        $this->addOption(
            'max-rsvps',
            null,
            InputOption::VALUE_REQUIRED,
            'Maximum number of RSVPs per event',
            '5'
        );
        $this->addOption(
            'min-comments',
            null,
            InputOption::VALUE_REQUIRED,
            'Minimum number of comments per event',
            '1'
        );
        $this->addOption(
            'max-comments',
            null,
            InputOption::VALUE_REQUIRED,
            'Maximum number of comments per event',
            '4'
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $minRsvps = (int) $input->getOption('min-rsvps');
        $maxRsvps = (int) $input->getOption('max-rsvps');
        $minComments = (int) $input->getOption('min-comments');
        $maxComments = (int) $input->getOption('max-comments');

        // Find all recurring events (events that have a parent)
        $recurringEvents = $this->em->createQuery(
            'SELECT e FROM App\Entity\Event e WHERE e.recurringOf IS NOT NULL'
        )->getResult();

        if (empty($recurringEvents)) {
            $output->writeln('<comment>No recurring events found to enhance.</comment>');

            return Command::SUCCESS;
        }

        // Get all users for random selection
        $allUsers = $this->userRepo->findAll();

        if (count($allUsers) < $minRsvps) {
            $output->writeln('<error>Not enough users in database to add RSVPs.</error>');

            return Command::FAILURE;
        }

        $rsvpCount = 0;
        $commentCount = 0;

        foreach ($recurringEvents as $event) {
            // Add RSVPs if event doesn't have any
            if ($event->getRsvp()->count() === 0) {
                $numRsvps = random_int($minRsvps, min($maxRsvps, count($allUsers)));
                $selectedUsers = $this->getRandomUsers($allUsers, $numRsvps);

                foreach ($selectedUsers as $user) {
                    $event->addRsvp($user);
                    ++$rsvpCount;
                }

                $this->em->persist($event);
            }

            // Add random comments
            $numComments = random_int($minComments, min($maxComments, count($allUsers)));
            for ($i = 0; $i < $numComments; ++$i) {
                $comment = new Comment();
                $comment->setEvent($event);
                $comment->setUser($this->getRandomUsers($allUsers, 1)[0]);
                $comment->setContent($this->getRandomComment());

                // Create timestamp between event creation and now
                $daysAfterEvent = random_int(0, 7);
                $comment->setCreatedAt(
                    (clone $event->getCreatedAt())->modify("+{$daysAfterEvent} days")
                );

                $this->em->persist($comment);
                ++$commentCount;
            }
        }

        $this->em->flush();

        $output->writeln(sprintf(
            '<info>Successfully added %d RSVPs and %d comments to %d recurring events.</info>',
            $rsvpCount,
            $commentCount,
            count($recurringEvents)
        ));

        // Run plugin fixtures
        foreach ($this->plugins as $plugin) {
            $plugin->loadPostExtendFixtures($output);
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<int, \App\Entity\User> $users
     *
     * @return array<int, \App\Entity\User>
     */
    private function getRandomUsers(array $users, int $count): array
    {
        $shuffled = $users;
        shuffle($shuffled);

        return array_slice($shuffled, 0, $count);
    }

    private function getRandomComment(): string
    {
        return self::SAMPLE_COMMENTS[array_rand(self::SAMPLE_COMMENTS)];
    }
}
