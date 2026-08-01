<?php declare(strict_types=1);

namespace Plugin\Glossary\DataFixtures;

use App\DataFixtures\AbstractFixture;
use App\Entity\ChangeProposal;
use App\Entity\ItemTag;
use App\Entity\ItemTagAssignment;
use App\Entity\PluginSettings;
use App\Entity\User;
use App\Review\FieldChange;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Review\GlossaryChangeTarget;

class GlossaryFixture extends AbstractFixture implements FixtureGroupInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating glossary ... ';

        $manager->persist($this->buildGlobalConfig());
        $tags = $this->buildTags($manager);

        $assignments = [];
        foreach ($this->getData() as [$phrase, $pinyin, $explanation, $tagKey, $user, $approved]) {
            $glossary = new Glossary();
            $glossary->setCreatedAt(new DateTimeImmutable());
            $glossary->setCreatedBy($user);
            $glossary->setApproved($approved);
            $glossary->setPhrase($phrase);
            $glossary->setPinyin($pinyin);
            $glossary->setExplanation($explanation);

            $manager->persist($glossary);
            $assignments[] = [$glossary, $tagKey];
        }
        $manager->flush();

        foreach ($assignments as [$glossary, $tagKey]) {
            foreach ([$tags[$tagKey], ...$tags[$tagKey]->getAncestors()] as $tag) {
                $assignment = new ItemTagAssignment();
                $assignment->setItemType(GlossaryTaggableTypeProvider::ITEM_TYPE);
                $assignment->setItemId((int) $glossary->getId());
                $assignment->setTag($tag);
                $manager->persist($assignment);
            }
        }
        $manager->flush();

        $this->seedPendingProposal($manager, $assignments[0][0], $tags);

        echo 'OK' . PHP_EOL;
    }

    /** @return array<int, ItemTag> */
    private function buildTags(ObjectManager $manager): array
    {
        $rows = [
            [0, 'Greeting', 'Begrüßung', null],
            [7, 'Informal', 'Umgangssprache', null],
            [1, 'Swearing', 'Schimpfen', 7],
            [2, 'Flirting', 'Flirten', 7],
            [3, 'Slang', 'Slang', 7],
            [4, 'Abbreviation', 'Abkürzung', null],
            [5, 'Regular', 'Regulär', null],
            [6, 'Idioms', 'Redewendungen', null],
        ];

        $tags = [];
        $position = 0;
        foreach ($rows as [$key, $en, $de, $parent]) {
            $tag = new ItemTag();
            $tag->setItemType(GlossaryTaggableTypeProvider::ITEM_TYPE);
            $tag->setLabels(['en' => $en, 'de' => $de]);
            $tag->setParent($parent === null ? null : ($tags[$parent] ?? null));
            $tag->setPosition($position++);
            $manager->persist($tag);
            $tags[$key] = $tag;
        }
        $manager->flush();

        return $tags;
    }

    /** @param array<int, ItemTag> $tags */
    private function seedPendingProposal(ObjectManager $manager, Glossary $entry, array $tags): void
    {
        $member = $manager->getRepository(User::class)->findOneBy(['email' => 'Adem.Lane@example.org']);
        if ($member === null) {
            return;
        }

        $proposal = new ChangeProposal();
        $proposal->setTargetType(GlossaryTaggableTypeProvider::ITEM_TYPE);
        $proposal->setTargetId((int) $entry->getId());
        $proposal->setProposedBy($member);
        $proposal->setChanges([
            new FieldChange(GlossaryChangeTarget::FIELD_EXPLANATION, $entry->getExplanation(), 'go away (very rude)'),
            new FieldChange(GlossaryChangeTarget::FIELD_TAG, (string) $tags[1]->getId(), (string) $tags[3]->getId()),
        ]);
        $manager->persist($proposal);
        $manager->flush();
    }

    private function buildGlobalConfig(): PluginSettings
    {
        $config = new PluginSettings();
        $config->setPluginKey('glossary');
        $config->setData([
            'secondaryEnabled' => true,
            'secondaryLabel' => 'Pinyin',
            'primaryLabel' => null,
            'definitionLabel' => null,
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
