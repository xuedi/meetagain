<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\UserBlock;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class UserBlockFixture extends Fixture implements FixtureGroupInterface
{
    private const array BLOCKS = [
        ['Belle Woods', 'Orlando Diggs', '-6 weeks'],
        ['Belle Woods', 'Drew Cano', '-9 days'],
    ];

    public static function getGroups(): array
    {
        return ['base'];
    }

    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    public function load(ObjectManager $manager): void
    {
        echo 'Creating user blocks ... ';

        $created = 0;
        foreach (self::BLOCKS as [$blockerName, $blockedName, $when]) {
            $blocker = $this->userRepository->findOneBy(['name' => $blockerName]);
            $blocked = $this->userRepository->findOneBy(['name' => $blockedName]);
            if ($blocker === null || $blocked === null) {
                continue;
            }

            $block = new UserBlock();
            $block->setBlocker($blocker);
            $block->setBlocked($blocked);
            $block->setCreatedAt(new DateTimeImmutable($when));

            $manager->persist($block);
            $created++;
        }

        $manager->flush();

        echo $created > 0 ? 'OK' . PHP_EOL : 'SKIP (users not found)' . PHP_EOL;
    }
}
