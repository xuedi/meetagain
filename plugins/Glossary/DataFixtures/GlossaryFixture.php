<?php declare(strict_types=1);

namespace Plugin\Glossary\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Glossary\Entity\Category;
use Plugin\Glossary\Entity\Glossary;

class GlossaryFixture extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating glossary ... ';
        $categoryGreetings = $this->getReference('glossary_Category_' . md5('Greetings'), Category::class);
        foreach ($this->getData() as [$phrase, $pinyin, $user]) {
            $glossary = new Glossary();
            $glossary->setCreatedAt(new DateTimeImmutable());
            $glossary->setCreatedBy($user);
            $glossary->setSuggestedBy($user);
            $glossary->setPhrase($phrase);
            $glossary->setPinyin($pinyin);
            $glossary->setCategory($categoryGreetings);

            $manager->persist($glossary);
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            CategoryFixture::class,
        ];
    }

    private function getData(): array
    {
        return [
            ['草泥马', 'cǎo ní mǎ', 1],
            ['干嘛', 'gàn má', 2],
            ['你吃了吗？', 'nǐ chī le ma?', 2],
            ['你好', 'nĭ hăo', 2],
        ];
    }

    public static function getGroups(): array
    {
        return ['Glossary'];
    }
}
