<?php declare(strict_types=1);

namespace Plugin\Filmclub;

use App\Entity\Link;
use App\Plugin;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Filmclub\Entity\Vote;
use Plugin\Filmclub\Entity\VoteBallot;
use Plugin\Filmclub\Repository\FilmRepository;
use Plugin\Filmclub\Repository\VoteRepository;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly VoteRepository $voteRepository,
        private readonly Environment $twig,
        private readonly EventRepository $eventRepository,
        private readonly FilmRepository $filmRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getPluginKey(): string
    {
        return 'filmclub';
    }

    public function getMenuLinks(): array
    {
        return [
            new Link(
                slug: $this->urlGenerator->generate('app_filmclub_filmlist'),
                name: 'Filme',
            ),
            new Link(
                slug: $this->urlGenerator->generate('app_filmclub_vote'),
                name: 'Vote',
            )
        ];
    }

    public function getEventTile(int $eventId): ?string
    {
        $vote = $this->voteRepository->findByEventId($eventId);

        return $this->twig->render('@Filmclub/tile/event.html.twig', [
            'vote' => $vote,
            'eventId' => $eventId,
        ]);
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
        $films = $this->filmRepository->findAll();
        if (count($films) === 0) {
            $output->writeln('<comment>Filmclub: No films found, skipping vote fixtures.</comment>');
            return;
        }

        $allUsers = $this->userRepository->findAll();
        if (count($allUsers) === 0) {
            $output->writeln('<comment>Filmclub: No users found, skipping vote fixtures.</comment>');
            return;
        }

        $adminUser = $this->userRepository->findOneBy(['name' => 'admin']);
        if ($adminUser === null) {
            $adminUser = $allUsers[0];
        }

        $voteCount = 0;
        $ballotCount = 0;

        // Create closed votes for past events
        $pastEvents = $this->eventRepository->getPastEvents(10);
        foreach ($pastEvents as $event) {
            // Skip if vote already exists for this event
            if ($this->voteRepository->findByEventId($event->getId()) !== null) {
                continue;
            }

            $vote = new Vote();
            $vote->setEventId($event->getId());
            $vote->setClosesAt(new DateTimeImmutable($event->getStart()->format('Y-m-d H:i:s') . ' -1 day'));
            $vote->setIsClosed(true);
            $vote->setCreatedAt(new DateTimeImmutable($event->getStart()->format('Y-m-d H:i:s') . ' -14 days'));
            $vote->setCreatedBy($adminUser->getId());
            $this->em->persist($vote);

            // Create 5-12 random ballots
            $voterCount = rand(5, min(12, count($allUsers)));
            $shuffledUsers = $allUsers;
            shuffle($shuffledUsers);
            $voters = array_slice($shuffledUsers, 0, $voterCount);

            // Pick 2-4 films that will receive votes
            $shuffledFilms = $films;
            shuffle($shuffledFilms);
            $votableFilms = array_slice($shuffledFilms, 0, rand(2, min(4, count($films))));

            foreach ($voters as $voter) {
                $ballot = new VoteBallot();
                $ballot->setVote($vote);
                $ballot->setFilm($votableFilms[array_rand($votableFilms)]);
                $ballot->setMemberId($voter->getId());
                $ballot->setCreatedAt(new DateTimeImmutable($event->getStart()->format('Y-m-d H:i:s') . ' -' . rand(2, 13) . ' days'));
                $this->em->persist($ballot);
                ++$ballotCount;
            }

            ++$voteCount;
        }

        // Create open vote for upcoming event
        $nextEventId = $this->eventRepository->getNextEventId();
        if ($nextEventId !== null) {
            $nextEvent = $this->eventRepository->find($nextEventId);
            if ($nextEvent !== null && $this->voteRepository->findByEventId($nextEvent->getId()) === null) {
                $vote = new Vote();
                $vote->setEventId($nextEvent->getId());
                $vote->setClosesAt(new DateTimeImmutable($nextEvent->getStart()->format('Y-m-d H:i:s') . ' -1 day'));
                $vote->setIsClosed(false);
                $vote->setCreatedAt(new DateTimeImmutable('-7 days'));
                $vote->setCreatedBy($adminUser->getId());
                $this->em->persist($vote);

                // Add 2-4 initial ballots
                $initialVoterCount = rand(2, min(4, count($allUsers)));
                $shuffledUsers = $allUsers;
                shuffle($shuffledUsers);
                $initialVoters = array_slice($shuffledUsers, 0, $initialVoterCount);

                $shuffledFilms = $films;
                shuffle($shuffledFilms);
                $votableFilms = array_slice($shuffledFilms, 0, rand(2, 3));

                foreach ($initialVoters as $voter) {
                    $ballot = new VoteBallot();
                    $ballot->setVote($vote);
                    $ballot->setFilm($votableFilms[array_rand($votableFilms)]);
                    $ballot->setMemberId($voter->getId());
                    $ballot->setCreatedAt(new DateTimeImmutable('-' . rand(1, 6) . ' days'));
                    $this->em->persist($ballot);
                    ++$ballotCount;
                }

                ++$voteCount;
            }
        }

        $this->em->flush();

        $output->writeln(sprintf(
            '<info>Filmclub: Created %d votes with %d ballots.</info>',
            $voteCount,
            $ballotCount
        ));
    }
}
