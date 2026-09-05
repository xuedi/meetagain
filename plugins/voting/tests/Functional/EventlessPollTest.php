<?php declare(strict_types=1);

namespace Plugin\Voting\Tests\Functional;

use App\Service\Item\AssociationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Voting\Cron\CloseExpiredPollsCron;
use Plugin\Voting\Service\PollService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Output\NullOutput;

class EventlessPollTest extends KernelTestCase
{
    public function testTheCronClosesAnEventlessPollWithoutAttachingAnything(): void
    {
        // Arrange
        self::bootKernel();
        $container = self::getContainer();
        $pollService = $container->get(PollService::class);
        $em = $container->get(EntityManagerInterface::class);
        $associations = $container->get(AssociationService::class);

        $poll = $pollService->create(null, 'photo', [999001, 999002], 7, 1);
        static::assertNull($poll->getEvent());

        $poll->setEndDate(new DateTimeImmutable('-1 day'));
        $em->flush();

        // Act
        $result = $container->get(CloseExpiredPollsCron::class)->runCronTask(new NullOutput());
        // Assert
        static::assertStringNotContainsString('1 errors', $result->message);

        $em->refresh($poll);
        static::assertNotNull($poll->getClosedAt(), 'poll was closed');
        static::assertSame([], $associations->eventIdsForItem('photo', 999001));
        static::assertSame([], $associations->eventIdsForItem('photo', 999002));
    }
}
