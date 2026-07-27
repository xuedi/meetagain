<?php declare(strict_types=1);

namespace Plugin\Films\DataFixtures;

use App\DataFixtures\AbstractFixture;
use App\DataFixtures\UserFixture;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Plugin\Films\Entity\Film;

class FilmFixture extends AbstractFixture implements FixtureGroupInterface
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating films ... ';

        $creator = $manager->getRepository(User::class)->findOneBy([
            'email' => str_replace(' ', '.', UserFixture::ADEM_LANE) . '@example.org',
        ]);
        if ($creator === null) {
            echo 'skipped' . PHP_EOL;

            return;
        }

        foreach ($this->getData() as [$title, $originalTitle, $year, $runtime, $genres, $description]) {
            $film = new Film();
            $film->setTitle($title);
            $film->setOriginalTitle($originalTitle);
            $film->setYear($year);
            $film->setRuntime($runtime);
            $film->setGenres($genres);
            $film->setDescription($description);
            $film->setCreatedBy((int) $creator->getId());
            $film->setCreatedAt(new DateTimeImmutable());

            $manager->persist($film);
        }
        $manager->flush();

        echo 'OK' . PHP_EOL;
    }

    public static function getGroups(): array
    {
        return ['plugin'];
    }

    /**
     * @return list<array{string, string|null, int, int, list<string>, string}>
     */
    private function getData(): array
    {
        return [
            ['Tokyo Story', '東京物語', 1953, 136, ['Drama'], 'An elderly couple visit their grown children in postwar Tokyo and find little room in their busy lives.'],
            ['Cleo from 5 to 7', 'Cleo de 5 a 7', 1962, 90, ['Drama'], 'A singer wanders Paris for two hours while waiting for a medical result that could change everything.'],
            ['Stalker', 'Сталкер', 1979, 161, ['Science Fiction', 'Drama'], 'A guide leads two men through a forbidden zone towards a room said to grant a visitor their deepest wish.'],
            ['Yi Yi', '一一', 2000, 173, ['Drama'], 'Three generations of a Taipei family drift through a year of weddings, illness and quiet reckoning.'],
            ['The Wages of Fear', 'Le salaire de la peur', 1953, 131, ['Thriller'], 'Four desperate men drive two trucks of nitroglycerine across South American mountain roads for a payout.'],
        ];
    }
}
