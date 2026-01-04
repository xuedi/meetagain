<?php declare(strict_types=1);

namespace Plugin\Filmclub\DataFixtures;

use App\DataFixtures\AbstractFixture;
use App\DataFixtures\EventFixture;
use App\DataFixtures\UserFixture;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Filmclub\Entity\Vote;
use Plugin\Filmclub\Entity\VoteBallot;
use Plugin\Filmclub\Repository\FilmRepository;

class VoteFixture extends AbstractFixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly FilmRepository $filmRepository,
        private readonly UserFixture $userFixture,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->start();

        $films = $this->filmRepository->findAll();
        if (count($films) === 0) {
            $this->stop();
            return;
        }

        $usernames = $this->userFixture->getUsernames();
        $adminUser = $this->getRefUser(UserFixture::ADMIN);

        // Create closed votes for past events
        $pastEvents = $this->eventRepository->getPastEvents(10);
        foreach ($pastEvents as $event) {
            $vote = new Vote();
            $vote->setEventId($event->getId());
            $vote->setClosesAt(new DateTimeImmutable($event->getStart()->format('Y-m-d H:i:s') . ' -1 day'));
            $vote->setIsClosed(true);
            $vote->setCreatedAt(new DateTimeImmutable($event->getStart()->format('Y-m-d H:i:s') . ' -14 days'));
            $vote->setCreatedBy($adminUser->getId());
            $manager->persist($vote);

            // Create 5-12 random ballots
            $ballotCount = rand(5, 12);
            $shuffledUsers = $usernames;
            shuffle($shuffledUsers);
            $voters = array_slice($shuffledUsers, 0, $ballotCount);

            // Pick 2-4 films that will receive votes (to make it realistic)
            $shuffledFilms = $films;
            shuffle($shuffledFilms);
            $votableFilms = array_slice($shuffledFilms, 0, rand(2, min(4, count($films))));

            foreach ($voters as $voterName) {
                $ballot = new VoteBallot();
                $ballot->setVote($vote);
                $ballot->setFilm($votableFilms[array_rand($votableFilms)]);
                $ballot->setMemberId($this->getRefUser($voterName)->getId());
                $ballot->setCreatedAt(new DateTimeImmutable($event->getStart()->format('Y-m-d H:i:s') . ' -' . rand(2, 13) . ' days'));
                $manager->persist($ballot);
            }

            $this->tick();
        }

        // Create open vote for upcoming event
        $nextEventId = $this->eventRepository->getNextEventId();
        if ($nextEventId !== null) {
            $nextEvent = $this->eventRepository->find($nextEventId);
            if ($nextEvent !== null) {
                $vote = new Vote();
                $vote->setEventId($nextEvent->getId());
                $vote->setClosesAt(new DateTimeImmutable($nextEvent->getStart()->format('Y-m-d H:i:s') . ' -1 day'));
                $vote->setIsClosed(false);
                $vote->setCreatedAt(new DateTimeImmutable('-7 days'));
                $vote->setCreatedBy($adminUser->getId());
                $manager->persist($vote);

                // Add 2-4 initial ballots (some users already voted)
                $initialVoterCount = rand(2, 4);
                $shuffledUsers = $usernames;
                shuffle($shuffledUsers);
                $initialVoters = array_slice($shuffledUsers, 0, $initialVoterCount);

                $shuffledFilms = $films;
                shuffle($shuffledFilms);
                $votableFilms = array_slice($shuffledFilms, 0, rand(2, 3));

                foreach ($initialVoters as $voterName) {
                    $ballot = new VoteBallot();
                    $ballot->setVote($vote);
                    $ballot->setFilm($votableFilms[array_rand($votableFilms)]);
                    $ballot->setMemberId($this->getRefUser($voterName)->getId());
                    $ballot->setCreatedAt(new DateTimeImmutable('-' . rand(1, 6) . ' days'));
                    $manager->persist($ballot);
                }

                $this->tick();
            }
        }

        $manager->flush();
        $this->stop();
    }

    public function getDependencies(): array
    {
        return [
            EventFixture::class,
            FilmFixture::class,
            UserFixture::class,
        ];
    }
}
