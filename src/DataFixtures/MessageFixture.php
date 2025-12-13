<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Message;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MessageFixture extends AbstractFixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->start();
        foreach ($this->getData() as [$time, $userSender, $userReceiver, $content, $wasRead]) {
            $msg = new Message();
            $msg->setCreatedAt(new DateTimeImmutable($time));
            $msg->setSender($this->getRefUser($userSender));
            $msg->setReceiver($this->getRefUser($userReceiver));
            $msg->setContent($content);
            $msg->setDeleted(false);
            $msg->setWasRead($wasRead);

            $manager->persist($msg);
        }
        $manager->flush();
        $this->stop();
    }

    public function getDependencies(): array
    {
        return [
            UserFixture::class,
        ];
    }

    private function getData(): array
    {
        return [
            [
                '2025-01-03 11:32:00',
                UserFixture::ADMIN,
                UserFixture::ABRAHAM_BAKER,
                'Hey, how are you doing?',
                true
            ],
            [
                '2025-01-03 14:15:00',
                UserFixture::ABRAHAM_BAKER,
                UserFixture::ADMIN,
                'Hi! I\'m good, thanks for asking. How about you?',
                true
            ],
            [
                '2025-01-03 15:20:00',
                UserFixture::ADMIN,
                UserFixture::ABRAHAM_BAKER,
                'I\'m doing great! Working on some new projects.',
                true
            ],
            [
                '2025-01-04 09:45:00',
                UserFixture::ABRAHAM_BAKER,
                UserFixture::ADMIN,
                'That sounds interesting! What kind of projects?',
                true
            ],
            [
                '2025-01-04 10:30:00',
                UserFixture::ADMIN,
                UserFixture::ABRAHAM_BAKER,
                'Mainly web development stuff. Pretty exciting!',
                true
            ],
            [
                '2025-01-05 13:20:00',
                UserFixture::ABRAHAM_BAKER,
                UserFixture::ADMIN,
                'Cool! I\'ve been thinking about getting into that too.',
                true,
            ],
            [
                '2025-01-05 14:45:00',
                UserFixture::ADMIN,
                UserFixture::ABRAHAM_BAKER,
                'I can give you some tips if you\'d like',
                true
            ],
            [
                '2025-01-06 11:10:00',
                UserFixture::ABRAHAM_BAKER,
                UserFixture::ADMIN,
                'That would be great! When are you free?',
                true
            ],
            [
                '2025-01-06 12:30:00',
                UserFixture::ADMIN,
                UserFixture::ABRAHAM_BAKER,
                'How about next week?',
                true
            ],
            [
                '2025-01-07 09:15:00',
                UserFixture::ABRAHAM_BAKER,
                UserFixture::ADMIN,
                'Perfect! Any specific day you prefer?',
                true
            ],
            [
                '2025-01-08 14:25:00',
                UserFixture::ABRAHAM_BAKER,
                UserFixture::ADMIN,
                'Tuesday? What time?',
                true
            ],
            [
                '2025-01-08 15:30:00',
                UserFixture::ADMIN,
                UserFixture::ABRAHAM_BAKER,
                'How about 2 PM?',
                true
            ],
            [
                '2025-01-09 11:45:00',
                UserFixture::ABRAHAM_BAKER,
                UserFixture::ADMIN,
                'Sounds good! Where should we meet?',
                true
            ],
            [
                '2025-01-09 13:50:00',
                UserFixture::ADMIN,
                UserFixture::ABRAHAM_BAKER,
                'There\'s a nice cafe downtown',
                true
            ],
            [
                '2025-01-10 10:15:00',
                UserFixture::ABRAHAM_BAKER,
                UserFixture::ADMIN,
                'The one on Main Street?',
                true
            ],
            [
                '2025-01-10 11:20:00',
                UserFixture::ADMIN,
                UserFixture::ABRAHAM_BAKER,
                'Yes, that\'s the one!',
                true
            ],
            [
                '2025-02-03 11:32:00',
                UserFixture::ADMIN,
                UserFixture::CRYSTAL_LIU,
                'hello, welcome to the group',
                true
            ],
            [
                '2025-02-03 11:33:15',
                UserFixture::CRYSTAL_LIU,
                UserFixture::ADMIN,
                'thank you for organizing the event',
                true
            ],
            [
                '2025-02-03 11:37:54',
                UserFixture::ADMIN,
                UserFixture::CRYSTAL_LIU,
                'you are welcome to join us again, dont forget to RSVP',
                true,
            ],
            [
                '2025-02-03 11:37:54',
                UserFixture::CRYSTAL_LIU,
                UserFixture::ADMIN,
                'When is the next meetup?',
                false
            ],
            [
                '2025-04-02 22:04:23',
                UserFixture::ALISA_HESTER,
                UserFixture::ADMIN,
                'Hello',
                false
            ],
            [
                '2025-04-02 22:05:36',
                UserFixture::ALISA_HESTER,
                UserFixture::ADMIN,
                'I lost my scarf last week, was it maybe found?',
                false
            ],
        ];
    }
}
