<?php declare(strict_types=1);

namespace Plugin\Filmclub\DataFixtures;

use App\DataFixtures\AbstractFixture;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmGenre;

class FilmFixture extends AbstractFixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['plugin'];
    }

    private const array TITLES = [
        'The Shawshank Redemption',
        'The Godfather',
        'The Dark Knight',
        'The Godfather Part II',
        '12 Angry Men',
        'Schindler\'s List',
        'The Lord of the Rings: The Return of the King',
        'Pulp Fiction',
        'The Lord of the Rings: The Fellowship of the Ring',
        'The Good, the Bad and the Ugly',
        'Forrest Gump',
        'Fight Club',
        'Inception',
        'The Lord of the Rings: The Two Towers',
        'Star Wars: Episode V - The Empire Strikes Back',
        'The Matrix',
        'Goodfellas',
        'One Flew Over the Cuckoo\'s Nest',
        'Se7en',
        'Seven Samurai',
        'Its a Wonderful Life',
        'The Silence of the Lambs',
        'City of God',
        'Saving Private Ryan',
        'Life Is Beautiful',
        'The Green Mile',
        'Interstellar',
        'Star Wars: Episode IV - A New Hope',
        'Terminator 2: Judgment Day',
        'Back to the Future',
        'Spirited Away',
        'The Pianist',
        'Psycho',
        'Parasite',
        'Leon: The Professional',
        'The Lion King',
        'Gladiator',
        'American History X',
        'The Departed',
        'The Usual Suspects',
        'The Prestige',
        'Casablanca',
        'Whiplash',
        'The Intouchables',
        'Modern Times',
        'Once Upon a Time in the West',
        'Hara-Kiri',
        'Grave of the Fireflies',
        'Rear Window',
        'Alien',
    ];

    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    public function load(ObjectManager $manager): void
    {
        echo 'Creating films ... ';

        // Get all users from database
        $users = $this->userRepository->findAll();
        if (empty($users)) {
            echo 'SKIP (no users found)' . PHP_EOL;
            return;
        }

        $titles = self::TITLES;
        shuffle($titles);
        $titles = array_slice($titles, 0, 30);

        foreach ($titles as $title) {
            $film = new Film();
            $film->setTitle($title);
            $film->setYear(rand(1950, 2024));
            $film->setRuntime(rand(80, 200));

            $genres = FilmGenre::cases();
            shuffle($genres);
            $selectedGenres = array_slice($genres, 0, rand(1, 3));
            $film->setGenres($selectedGenres);

            $randomUser = $users[array_rand($users)];
            $film->setCreatedBy($randomUser->getId());
            $film->setCreatedAt(new DateTimeImmutable(sprintf('-%d days', rand(1, 100))));

            $manager->persist($film);
        }

        $manager->flush();
        echo 'OK' . PHP_EOL;
    }
}
