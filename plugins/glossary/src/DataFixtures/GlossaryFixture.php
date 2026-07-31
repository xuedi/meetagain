<?php declare(strict_types=1);

namespace Plugin\Glossary\DataFixtures;

use App\DataFixtures\AbstractFixture;
use App\Entity\ChangeProposal;
use App\Entity\ItemCategoryAssignment;
use App\Entity\PluginSettings;
use App\Entity\User;
use App\Review\FieldChange;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryCategorizableTypeProvider;
use Plugin\Glossary\Review\GlossaryChangeTarget;

class GlossaryFixture extends AbstractFixture implements FixtureGroupInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating glossary ... ';

        $manager->persist($this->buildGlobalConfig());

        $assignments = [];
        foreach ($this->getData() as [$phrase, $pinyin, $explanation, $category, $user, $approved]) {
            $glossary = new Glossary();
            $glossary->setCreatedAt(new DateTimeImmutable());
            $glossary->setCreatedBy($user);
            $glossary->setApproved($approved);
            $glossary->setPhrase($phrase);
            $glossary->setPinyin($pinyin);
            $glossary->setExplanation($explanation);

            $manager->persist($glossary);
            $assignments[] = [$glossary, $category];
        }
        $manager->flush();

        foreach ($assignments as [$glossary, $categoryId]) {
            $assignment = new ItemCategoryAssignment();
            $assignment->setItemType(GlossaryCategorizableTypeProvider::ITEM_TYPE);
            $assignment->setItemId((int) $glossary->getId());
            $assignment->setCategoryId($categoryId);
            $manager->persist($assignment);
        }
        $manager->flush();

        $this->seedPendingProposal($manager, $assignments[0][0]);

        echo 'OK' . PHP_EOL;
    }

    private function seedPendingProposal(ObjectManager $manager, Glossary $entry): void
    {
        $member = $manager->getRepository(User::class)->findOneBy(['email' => 'Adem.Lane@example.org']);
        if ($member === null) {
            return;
        }

        $proposal = new ChangeProposal();
        $proposal->setTargetType(GlossaryCategorizableTypeProvider::ITEM_TYPE);
        $proposal->setTargetId((int) $entry->getId());
        $proposal->setProposedBy($member);
        $proposal->setChanges([
            new FieldChange(GlossaryChangeTarget::FIELD_EXPLANATION, $entry->getExplanation(), 'go away (very rude)'),
            new FieldChange(GlossaryChangeTarget::FIELD_CATEGORY, '1', '3'),
        ]);
        $manager->persist($proposal);
        $manager->flush();
    }

    private function buildGlobalConfig(): PluginSettings
    {
        $labels = [
            [0, 'Greeting', 'Begrüßung', null],
            [1, 'Swearing', 'Schimpfen', 0],
            [2, 'Flirting', 'Flirten', 0],
            [3, 'Slang', 'Slang', 0],
            [4, 'Abbreviation', 'Abkürzung', null],
            [5, 'Regular', 'Regulär', null],
            [6, 'Idioms', 'Redewendungen', null],
        ];

        $categories = [];
        foreach ($labels as [$id, $en, $de, $group]) {
            $row = ['id' => $id, 'labels' => ['en' => $en, 'de' => $de]];
            if ($group !== null) {
                $row['group'] = $group;
            }
            $categories[] = $row;
        }

        $config = new PluginSettings();
        $config->setPluginKey('glossary');
        $config->setData([
            'secondaryEnabled' => true,
            'secondaryLabel' => 'Pinyin',
            'primaryLabel' => null,
            'definitionLabel' => null,
            'taxonomy' => [
                'categoriesEnabled' => true,
                'tagsEnabled' => false,
                'categoryGroups' => [
                    ['id' => 0, 'labels' => ['en' => 'Informal', 'de' => 'Umgangssprache']],
                ],
                'categories' => $categories,
                'tags' => [],
            ],
        ]);
        $config->setUpdatedAt(new DateTimeImmutable());

        return $config;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: int, 4: int, 5: bool}>
     */
    private function getData(): array
    {
        return [
            ['草泥马',     'cǎo ní mǎ',     'fuck off',                                                                  1, 1, true],
            ['干嘛',       'gàn má',        'how is it going?',                                                          0, 2, true],
            ['你吃了吗？', 'nǐ chī le ma?', 'have you heating?',                                                         0, 2, true],
            ['你好',       'nĭ hăo',        'hello nobody uses anymore, you can use when seeing your ex after 10 years', 0, 2, false],
        ];
    }

    public static function getGroups(): array
    {
        return ['plugin'];
    }
}
