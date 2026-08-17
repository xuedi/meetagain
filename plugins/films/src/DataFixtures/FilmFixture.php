<?php declare(strict_types=1);

namespace Plugin\Films\DataFixtures;

use App\DataFixtures\AbstractFixture;
use App\DataFixtures\UserFixture;
use App\Entity\ItemTag;
use App\Entity\ItemTagAssignment;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Plugin\Films\Entity\Film;
use Plugin\Films\Service\FilmService;
use Plugin\Films\Service\FixturePosterService;

class FilmFixture extends AbstractFixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly FixturePosterService $fixturePosterService,
    ) {}

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

        $tags = $this->buildTags($manager);

        foreach ($this->getData() as [$title, $originalTitle, $year, $runtime, $genres, $description, $tagIds, $poster]) {
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
            $manager->flush();

            $this->fixturePosterService->attach($film, $poster, $creator);

            $wanted = [];
            foreach ($tagIds as $tagKey) {
                foreach ([$tags[$tagKey], ...$tags[$tagKey]->getAncestors()] as $tag) {
                    $wanted[(int) $tag->getId()] = $tag;
                }
            }

            foreach ($wanted as $tag) {
                $assignment = new ItemTagAssignment();
                $assignment->setItemType(FilmService::ITEM_TYPE);
                $assignment->setItemId((int) $film->getId());
                $assignment->setTag($tag);
                $manager->persist($assignment);
            }
        }
        $manager->flush();

        echo 'OK' . PHP_EOL;
    }

    /** @return array<int, ItemTag> */
    private function buildTags(ObjectManager $manager): array
    {
        $rows = [
            [0, 'Drama', 'Drama', null],
            [1, 'Family drama', 'Familiendrama', 0],
            [2, 'Road movie', 'Roadmovie', 0],
            [3, 'Science fiction', 'Science-Fiction', null],
            [4, 'Feature', 'Spielfilm', null],
            [5, 'Short', 'Kurzfilm', null],
        ];

        $tags = [];
        $position = 0;
        foreach ($rows as [$key, $en, $de, $parent]) {
            $tag = new ItemTag();
            $tag->setItemType(FilmService::ITEM_TYPE);
            $tag->setLabels(['en' => $en, 'de' => $de]);
            $tag->setParent($parent === null ? null : ($tags[$parent] ?? null));
            $tag->setPosition($position++);
            $manager->persist($tag);
            $tags[$key] = $tag;
        }
        $manager->flush();

        return $tags;
    }

    public static function getGroups(): array
    {
        return ['plugin'];
    }

    /**
     * @return list<array{string, string|null, int, int, list<string>, string, list<int>, string|null}>
     */
    private function getData(): array
    {
        return [
            ['Tokyo Story', '東京物語', 1953, 136, ['Drama'], 'An elderly couple visit their grown children in postwar Tokyo and find little room in their busy lives.', [0, 1], 'tokyo-story-1953.jpg'],
            ['Cleo from 5 to 7', 'Cleo de 5 a 7', 1962, 90, ['Drama'], 'A singer wanders Paris for two hours while waiting for a medical result that could change everything.', [0], 'cleo-from-5-to-7-1962.jpg'],
            ['Stalker', 'Сталкер', 1979, 161, ['Science Fiction', 'Drama'], 'A guide leads two men through a forbidden zone towards a room said to grant a visitor their deepest wish.', [3], 'stalker-1979.jpg'],
            ['Yi Yi', '一一', 2000, 173, ['Drama'], 'Three generations of a Taipei family drift through a year of weddings, illness and quiet reckoning.', [0, 1], 'yi-yi-2000.jpg'],
            ['The Wages of Fear', 'Le salaire de la peur', 1953, 131, ['Thriller'], 'Four desperate men drive two trucks of nitroglycerine across South American mountain roads for a payout.', [0, 2], 'the-wages-of-fear-1953.jpg'],
            ['Bicycle Thieves', 'Ladri di biciclette', 1948, 89, ['Drama'], 'A father and his small son search postwar Rome for the stolen bicycle his new job depends on.', [0, 1], 'bicycle-thieves-1948.jpg'],
            ['Rashomon', '羅生門', 1950, 88, ['Drama', 'Crime'], 'Four witnesses to a killing in a forest each tell a version of the day that flatters only themselves.', [0], 'rashomon-1950.jpg'],
            ['The 400 Blows', 'Les quatre cents coups', 1959, 99, ['Drama'], 'A Paris schoolboy nobody has time for drifts from truancy into petty theft and out towards the sea.', [0, 1], 'the-400-blows-1959.jpg'],
            ['Solaris', 'Солярис', 1972, 167, ['Science Fiction', 'Drama'], 'A psychologist sent to a station above an ocean planet meets a visitor his own memory appears to have made.', [3], 'solaris-1972.jpg'],
            ['Paris, Texas', null, 1984, 145, ['Drama'], 'A man walks out of the desert after four missing years and drives west to explain himself to his family.', [0, 2], 'paris-texas-1984.jpg'],
            ['Close-Up', 'نمای نزدیک', 1990, 98, ['Drama'], 'A film-struck man is tried for impersonating a famous director, and restages his own deception for the camera.', [0], 'close-up-1990.jpg'],
            ['Come and See', 'Иди и смотри', 1985, 142, ['Drama', 'War'], 'A boy joins the partisans in occupied Belarus and is aged past recognition by what he walks into.', [0], 'come-and-see-1985.jpg'],
            ['Wings of Desire', 'Der Himmel über Berlin', 1987, 128, ['Drama', 'Fantasy'], 'Two angels listen to the private thoughts of a divided Berlin until one of them wishes to be mortal.', [0], 'wings-of-desire-1987.jpg'],
            ['Pather Panchali', 'পথের পাঁচালী', 1955, 125, ['Drama'], 'A Bengali village family lives through hunger, monsoon and loss while the father looks for work elsewhere.', [0, 1], 'pather-panchali-1955.jpg'],
            ['La Strada', 'La strada', 1954, 108, ['Drama'], 'A young woman is sold to a travelling strongman and follows his roadside act across a bleak Italy.', [0, 2], 'la-strada-1954.jpg'],
        ];
    }
}
