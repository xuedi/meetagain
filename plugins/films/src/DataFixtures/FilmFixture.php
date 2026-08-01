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

        $tags = $this->buildTags($manager);

        foreach ($this->getData() as [$title, $originalTitle, $year, $runtime, $genres, $description, $tagIds]) {
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
     * @return list<array{string, string|null, int, int, list<string>, string, list<int>}>
     */
    private function getData(): array
    {
        return [
            ['Tokyo Story', '東京物語', 1953, 136, ['Drama'], 'An elderly couple visit their grown children in postwar Tokyo and find little room in their busy lives.', [0, 1]],
            ['Cleo from 5 to 7', 'Cleo de 5 a 7', 1962, 90, ['Drama'], 'A singer wanders Paris for two hours while waiting for a medical result that could change everything.', [0]],
            ['Stalker', 'Сталкер', 1979, 161, ['Science Fiction', 'Drama'], 'A guide leads two men through a forbidden zone towards a room said to grant a visitor their deepest wish.', [3]],
            ['Yi Yi', '一一', 2000, 173, ['Drama'], 'Three generations of a Taipei family drift through a year of weddings, illness and quiet reckoning.', [0, 1]],
            ['The Wages of Fear', 'Le salaire de la peur', 1953, 131, ['Thriller'], 'Four desperate men drive two trucks of nitroglycerine across South American mountain roads for a payout.', [0, 2]],
        ];
    }
}
