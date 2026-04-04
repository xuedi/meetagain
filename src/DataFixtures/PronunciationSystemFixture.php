<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\PronunciationSystem;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class PronunciationSystemFixture extends AbstractFixture implements FixtureGroupInterface
{
    private const array SYSTEMS = [
        ['Chinese / Mandarin', 'Pinyin',               'má po dòu fu'],
        ['Japanese',           'Rōmaji',               'ra-men'],
        ['Arabic',             'Romanisation',         'kus-kus'],
        ['Korean',             'Revised Romanisation', 'bi-bim-bap'],
        ['Thai',               'RTGS',                 'phàt thai'],
        ['Hindi',              'IAST',                 'bi-ryā-nī'],
        ['Greek',              'Greeklish',            'mu-sa-kás'],
    ];

    public function load(ObjectManager $manager): void
    {
        $this->start();
        foreach (self::SYSTEMS as [$language, $name, $example]) {
            $system = new PronunciationSystem();
            $system->setLanguage($language);
            $system->setName($name);
            $system->setExample($example);
            $manager->persist($system);
        }
        $manager->flush();
        $this->stop();
    }

    public static function getGroups(): array
    {
        return ['install'];
    }
}
