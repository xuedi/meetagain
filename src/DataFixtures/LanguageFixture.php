<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class LanguageFixture extends AbstractFixture implements FixtureGroupInterface
{
    // Keep constants for backward compatibility in other fixtures
    public const string ENGLISH = 'en';
    public const string GERMAN = 'de';
    public const string CHINESE = 'cn';

    public function load(ObjectManager $manager): void
    {
        $this->start();

        $languages = [
            ['code' => self::ENGLISH, 'name' => 'English', 'sortOrder' => 1],
            ['code' => self::GERMAN, 'name' => 'German', 'sortOrder' => 2],
            ['code' => self::CHINESE, 'name' => 'Chinese', 'sortOrder' => 3],
        ];

        foreach ($languages as $data) {
            $language = new Language();
            $language->setCode($data['code']);
            $language->setName($data['name']);
            $language->setEnabled(true);
            $language->setSortOrder($data['sortOrder']);

            $manager->persist($language);
            $this->tick();
        }

        $manager->flush();
        $this->stop();
    }

    public static function getGroups(): array
    {
        return ['install'];
    }
}
