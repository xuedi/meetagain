<?php declare(strict_types=1);

namespace Plugin\Glossary\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Glossary\Entity\Category;
use Plugin\Glossary\Entity\Glossary;

class GlossaryFixture extends Fixture implements FixtureGroupInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating glossary ... ';
        foreach ($this->getData() as [$phrase, $pinyin, $category, $user, $approved]) {
            $glossary = new Glossary();
            $glossary->setCreatedAt(new DateTimeImmutable());
            $glossary->setCreatedBy($user);
            $glossary->setApproved($approved);
            $glossary->setPhrase($phrase);
            $glossary->setPinyin($pinyin);
            $glossary->setCategory($category);

            $manager->persist($glossary);
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    private function getData(): array
    {
        return [
            [
                '草泥马',
                'cǎo ní mǎ',
                Category::Swearing,
                1,
                true,
            ],
            [
                '干嘛',
                'gàn má',
                Category::Greeting,
                2,
                true,
            ],
            [
                '你吃了吗？',
                'nǐ chī le ma?',
                Category::Greeting,
                2,
                true,
            ],
            [
                '你好',
                'nĭ hăo',
                Category::Greeting,
                2,
                false,
            ],
        ];
    }

    public static function getGroups(): array
    {
        return ['Glossary'];
    }
}
