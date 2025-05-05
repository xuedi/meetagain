<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Comment;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CommentFixture extends Fixture implements DependentFixtureInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        foreach ($this->getData() as [$event, $date, $user, $msg]) {
            $comment = new Comment();
            $comment->setEvent($this->getReference('event_' . md5((string)$event)));
            $comment->setUser($this->getReference('user_' . md5((string)$user)));
            $comment->setCreatedAt(new DateTimeImmutable($date));
            $comment->setContent($msg);

            $manager->persist($comment);
        }


        $manager->flush();
    }

    #[\Override]
    public function getDependencies(): array
    {
        return [
            UserFixture::class,
            EventFixture::class,
        ];
    }

    private function getData(): array
    {
        $event = 'Let\'s meet up and talk Chinese!';
        return [
            [$event, '2015-02-27 09:00', 'xuedi', 'Cool event'],
            [$event, '2015-02-27 09:03', 'yimu', 'it was, but very lonely'],
            [$event, '2015-02-27 09:07', 'xuedi', 'yeah, no one came it was just us :cry:'],
            [$event, '2015-02-27 09:08', 'yimu', 'true, but a start is a start'],
            [$event, '2015-02-27 14:32', 'xuedi', 'i hope next time more people will come'],
            [$event, '2015-02-28 15:00', 'Crystal Liu', 'i was there, have you forgotten about me? :angry:'],
        ];
    }
}
