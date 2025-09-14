<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\EventTranslation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Filesystem\Filesystem;

class EventTranslationFixture extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly Filesystem $fs,
    ) {}

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating eventTranslations ... ';
        foreach ($this->getData() as $data) {
            [$language, $title, $description] = $data;

            $eventTranslation = new EventTranslation();
            $eventTranslation->setEvent($this->getReference('event_' . md5((string) $title), Event::class));
            $eventTranslation->setLanguage($language);
            $eventTranslation->setTitle($title);
            $eventTranslation->setDescription($description);

            $manager->persist($eventTranslation);
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            EventFixture::class,
        ];
    }

    private function getData(): array
    {
        return [
            [
                'en',
                'Let\'s meet up and talk Chinese!',
                $this->getBlob('First'),
            ],
            [
                'de',
                'Let\'s meet up and talk Chinese!',
                'First-de',
            ],
            [
                'cn',
                'Let\'s meet up and talk Chinese!',
                'First-cn',
            ],
            [
                'en',
                'Spicy Chinese dinner at a Sichuan restaurant',
                $this->getBlob('SpicyChinese'),
            ],
            [
                'de',
                'Spicy Chinese dinner at a Sichuan restaurant',
                'SpicyChinese-de',
            ],
            [
                'cn',
                'Spicy Chinese dinner at a Sichuan restaurant',
                'SpicyChinese-cn',
            ],
            [
                'en',
                '中秋节 - Mid Autumn festival',
                $this->getBlob('MidAutumnFestival'),
            ],
            [
                'de',
                '中秋节 - Mid Autumn festival',
                'MidAutumnFestival-de',
            ],
            [
                'cn',
                '中秋节 - Mid Autumn festival',
                'MidAutumnFestival-cn',
            ],
            [
                'en',
                '下馆子！Let’s go eat!',
                $this->getBlob('LetsGoEat'),
            ],
            [
                'de',
                '下馆子！Let’s go eat!',
                'LetsGoEat-de',
            ],
            [
                'cn',
                '下馆子！Let’s go eat!',
                'LetsGoEat-cn',
            ],
            [
                'en',
                'Outdoor Meetup at Himmelbeet',
                $this->getBlob('himmelbeet_en'),
            ],
            [
                'de',
                'Outdoor Meetup at Himmelbeet',
                $this->getBlob('himmelbeet_de'),
            ],
            [
                'cn',
                'Outdoor Meetup at Himmelbeet',
                $this->getBlob('himmelbeet_cn'),
            ],
            [
                'en',
                '定期活动 - Regular meetup',
                $this->getBlob('current_en'),
            ],
            [
                'de',
                '定期活动 - Regular meetup',
                $this->getBlob('current_de'),
            ],
            [
                'cn',
                '定期活动 - Regular meetup',
                $this->getBlob('current_cn'),
            ],
            [
                'en',
                '生日快乐 - Meetup get one year older',
                'TEST - yearly birthday meetup event - en',
            ],
            [
                'de',
                '生日快乐 - Meetup get one year older',
                'TEST - yearly birthday meetup event - de',
            ],
            [
                'cn',
                '生日快乐 - Meetup get one year older',
                'TEST - yearly birthday meetup event - cn',
            ],
        ];
    }

    private function getBlob(string $string): string
    {
        return $this->fs->readFile(__DIR__ . "/blobs/Event_$string.txt");
    }
}
