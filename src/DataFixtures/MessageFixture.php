<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Activity;
use App\Entity\Message;
use App\Entity\User;
use App\Entity\ActivityType;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MessageFixture extends Fixture implements DependentFixtureInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        foreach ($this->getData() as [$time, $userSender, $userReceiver, $content, $wasRead]) {
            $msg = new Message();
            $msg->setCreatedAt(new DateTimeImmutable($time));
            $msg->setSender($this->getReference('user_' . md5((string) $userSender), User::class));
            $msg->setReceiver($this->getReference('user_' . md5((string) $userReceiver), User::class));
            $msg->setContent($content);
            $msg->setDeleted(false);
            $msg->setWasRead($wasRead);

            $manager->persist($msg);
        }
        $manager->flush();
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            UserFixture::class,
        ];
    }

    private function getData(): array
    {
        return [
            ['2025-01-03 11:32:00', 'xuedi', 'Abraham Baker', 'Hey, how are you doing?', true],
            ['2025-01-03 14:15:00', 'Abraham Baker', 'xuedi', 'Hi! I\'m good, thanks for asking. How about you?', true],
            ['2025-01-03 15:20:00', 'xuedi', 'Abraham Baker', 'I\'m doing great! Working on some new projects.', true],
            ['2025-01-04 09:45:00', 'Abraham Baker', 'xuedi', 'That sounds interesting! What kind of projects?', true],
            ['2025-01-04 10:30:00', 'xuedi', 'Abraham Baker', 'Mainly web development stuff. Pretty exciting!', true],
            ['2025-01-05 13:20:00', 'Abraham Baker', 'xuedi', 'Cool! I\'ve been thinking about getting into that too.', true],
            ['2025-01-05 14:45:00', 'xuedi', 'Abraham Baker', 'I can give you some tips if you\'d like', true],
            ['2025-01-06 11:10:00', 'Abraham Baker', 'xuedi', 'That would be great! When are you free?', true],
            ['2025-01-06 12:30:00', 'xuedi', 'Abraham Baker', 'How about next week?', true],
            ['2025-01-07 09:15:00', 'Abraham Baker', 'xuedi', 'Perfect! Any specific day you prefer?', true],
            ['2025-01-08 14:25:00', 'Abraham Baker', 'xuedi', 'Tuesday? What time?', true],
            ['2025-01-08 15:30:00', 'xuedi', 'Abraham Baker', 'How about 2 PM?', true],
            ['2025-01-09 11:45:00', 'Abraham Baker', 'xuedi', 'Sounds good! Where should we meet?', true],
            ['2025-01-09 13:50:00', 'xuedi', 'Abraham Baker', 'There\'s a nice cafe downtown', true],
            ['2025-01-10 10:15:00', 'Abraham Baker', 'xuedi', 'The one on Main Street?', true],
            ['2025-01-10 11:20:00', 'xuedi', 'Abraham Baker', 'Yes, that\'s the one!', true],
            ['2025-02-03 11:32:00', 'xuedi', 'Crystal Liu', 'hello, welcome to the group', true],
            ['2025-02-03 11:33:15', 'Crystal Liu', 'xuedi', 'thank you for organizing the event', true],
            ['2025-02-03 11:37:54', 'xuedi', 'Crystal Liu', 'you are welcome to join us again, dont forget to RSVP', true],
            ['2025-02-03 11:37:54', 'Crystal Liu', 'xuedi', 'When is the next meetup?', false],
            ['2025-04-02 22:04:23', 'Alisa Hester', 'xuedi', 'Hello', false],
            ['2025-04-02 22:05:36', 'Alisa Hester', 'xuedi', 'I lost my scarf last week, was it maybe found?', false],
        ];
    }
}
