<?php declare(strict_types=1);

namespace Plugin\Glossary\DataFixtures;

use App\DataFixtures\AbstractFixture;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Glossary\Entity\Glossary;

class GlossaryFixture extends AbstractFixture implements FixtureGroupInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating glossary ... ';
        foreach ($this->getData() as [$phrase, $pinyin, $explanation, $category, $user, $approved]) {
            $glossary = new Glossary();
            $glossary->setCreatedAt(new DateTimeImmutable());
            $glossary->setCreatedBy($user);
            $glossary->setApproved($approved);
            $glossary->setPhrase($phrase);
            $glossary->setPinyin($pinyin);
            $glossary->setExplanation($explanation);
            $glossary->setCategory($category);

            $manager->persist($glossary);
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    /**
     * Category ids are stable (Greeting = 0, Swearing = 1, ...) so a config seeded with the
     * matching category ids lines up with these entries.
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
