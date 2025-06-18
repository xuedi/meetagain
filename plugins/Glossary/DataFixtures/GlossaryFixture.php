<?php declare(strict_types=1);

namespace Plugin\Glossary\DataFixtures;

use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Glossary\Entity\Glossary;

class GlossaryFixture extends Fixture implements DependentFixtureInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating config ... ';
        foreach ($this->getData() as [$phrase, $user]) {
            $glossary = new Glossary();
            $glossary->setCreatedAt(new DateTimeImmutable());
            $glossary->setCreatedBy($user);
            $glossary->setPhrase($phrase);

            $manager->persist($glossary);
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [];
    }

    private function getData(): array
    {
        return [
            ['Ni Hao', 1],
            ['Ga Ma', 2],
        ];
    }
}
