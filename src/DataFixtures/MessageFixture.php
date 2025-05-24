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
        foreach ($this->getData() as [$time, $userSender, $userReceiver, $content]) {
            $msg = new Message();
            $msg->setCreatedAt(new DateTimeImmutable($time));
            $msg->setSender($this->getReference('user_' . md5((string) $userSender), User::class));
            $msg->setReceiver($this->getReference('user_' . md5((string) $userReceiver), User::class));
            $msg->setContent($content);

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
            ['2025-01-03 11:32:00', 'xuedi', 'Abraham Baker', 'Hey, how are you doing?'],
            ['2025-01-03 14:15:00', 'Abraham Baker', 'xuedi', 'Hi! I\'m good, thanks for asking. How about you?'],
            ['2025-01-03 15:20:00', 'xuedi', 'Abraham Baker', 'I\'m doing great! Working on some new projects.'],
            ['2025-01-04 09:45:00', 'Abraham Baker', 'xuedi', 'That sounds interesting! What kind of projects?'],
            ['2025-01-04 10:30:00', 'xuedi', 'Abraham Baker', 'Mainly web development stuff. Pretty exciting!'],
            ['2025-01-05 13:20:00', 'Abraham Baker', 'xuedi', 'Cool! I\'ve been thinking about getting into that too.'],
            ['2025-01-05 14:45:00', 'xuedi', 'Abraham Baker', 'I can give you some tips if you\'d like'],
            ['2025-01-06 11:10:00', 'Abraham Baker', 'xuedi', 'That would be great! When are you free?'],
            ['2025-01-06 12:30:00', 'xuedi', 'Abraham Baker', 'How about next week?'],
            ['2025-01-07 09:15:00', 'Abraham Baker', 'xuedi', 'Perfect! Any specific day you prefer?'],
            ['2025-01-07 10:20:00', 'xuedi', 'Abraham Baker', 'Tuesday would work best for me'],
            ['2025-01-08 14:25:00', 'Abraham Baker', 'xuedi', 'Tuesday works! What time?'],
            ['2025-01-08 15:30:00', 'xuedi', 'Abraham Baker', 'How about 2 PM?'],
            ['2025-01-09 11:45:00', 'Abraham Baker', 'xuedi', 'Sounds good! Where should we meet?'],
            ['2025-01-09 13:50:00', 'xuedi', 'Abraham Baker', 'There\'s a nice cafe downtown'],
            ['2025-01-10 10:15:00', 'Abraham Baker', 'xuedi', 'The one on Main Street?'],
            ['2025-01-10 11:20:00', 'xuedi', 'Abraham Baker', 'Yes, that\'s the one!'],
            ['2025-01-11 09:30:00', 'Abraham Baker', 'xuedi', 'Great! Looking forward to it'],
            ['2025-01-12 14:40:00', 'xuedi', 'Abraham Baker', 'Me too! See you then'],
            ['2025-01-12 15:45:00', 'Abraham Baker', 'xuedi', 'See you! Thanks again'],
            ['2025-02-03 11:32:00', 'xuedi', 'Crystal Liu', 'hello, welcome to the group'],
            ['2025-02-03 11:33:15', 'Crystal Liu', 'xuedi', 'thank you for organizing the event'],
            ['2025-02-03 11:37:54', 'xuedi', 'Crystal Liu', 'you are welcome to join us again, dont forget to RSVP'],
        ];
    }
}
