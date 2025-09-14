<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CommentFixture extends Fixture implements DependentFixtureInterface
{
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating comments ... ';
        foreach ($this->getData() as [$event, $date, $user, $msg]) {
            $comment = new Comment();
            $comment->setEvent($this->getReference('event_' . md5((string) $event), Event::class));
            $comment->setUser($this->getReference('user_' . md5((string) $user), User::class));
            $comment->setCreatedAt(new DateTimeImmutable($date));
            $comment->setContent($msg);

            $manager->persist($comment);
        }
        $manager->flush();
        echo 'OK' . PHP_EOL;
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
            [$event, '2015-02-27 09:00', 'admin',       'Cool event'],
            [$event, '2015-02-27 09:03', 'Adem Lane',   'it was, but very lonely'],
            [$event, '2015-02-27 09:07', 'admin',       'yeah, no one came it was just us :cry:'],
            [$event, '2015-02-27 09:08', 'Adem Lane',   'true, but a start is a start'],
            [$event, '2015-02-27 14:32', 'admin',       'i hope next time more people will come'],
            [$event, '2015-02-28 15:00', 'Crystal Liu', 'i was there, have you forgotten about me? :angry:'],
        ];
    }
}
