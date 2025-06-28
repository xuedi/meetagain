<?php declare(strict_types=1);

namespace Plugin\Glossary\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Glossary\Entity\Category;
use Plugin\Glossary\Entity\Glossary;

class CategoryFixture extends Fixture implements FixtureGroupInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating glossary_category ... ';
        foreach ($this->getData() as [$name]) {
            $category = new Category();
            $category->setName($name);
            $category->setCreatedAt(new DateTimeImmutable());
            $category->setCreatedBy(2);

            $manager->persist($category);
            $this->addReference('glossary_Category_' . md5((string)$name), $category);
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    private function getData(): array
    {
        return [
            ['Greetings'],
            ['Swearing'],
            ['InternetSlang'],
            ['Flirting'],
        ];
    }

     public static function getGroups(): array
     {
         return ['Glossary'];
     }
}
