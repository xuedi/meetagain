<?php declare(strict_types=1);

namespace Module\Email\Tests\Functional;

use DateTimeImmutable;
use Module\Email\Contract\MailerInterface;
use Module\Email\Internal\Entity\EmailQueue;
use Module\Email\Internal\Repository\EmailQueueRepository;
use Module\Email\Tests\Stub\TriggeredEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class QueueClaimTest extends KernelTestCase
{
    use SeedsTemplates;

    private const array RECIPIENTS = ['one@module-test.example', 'two@module-test.example', 'three@module-test.example'];

    public function testTwoSendersClaimDisjointRows(): void
    {
        // Arrange
        $repository = $this->queueThreeMails();
        $now = new DateTimeImmutable();

        // Act
        $first = $repository->claimPending(2, $now, $now->modify('-600 seconds'));
        $second = $repository->claimPending(2, $now, $now->modify('-600 seconds'));

        // Assert
        self::assertCount(2, $first);
        self::assertCount(1, $second);
        self::assertSame([], array_intersect($this->ids($first), $this->ids($second)));
    }

    public function testAClaimOlderThanTheLeaseIsTakenOver(): void
    {
        // Arrange
        $repository = $this->queueThreeMails();
        $claimedAt = new DateTimeImmutable('-20 minutes');
        $repository->claimPending(3, $claimedAt, $claimedAt->modify('-600 seconds'));
        $now = new DateTimeImmutable();

        // Act
        $takenOver = $repository->claimPending(3, $now, $now->modify('-600 seconds'));

        // Assert
        self::assertCount(3, $takenOver);
    }

    private function queueThreeMails(): EmailQueueRepository
    {
        self::bootKernel();
        self::seedTemplates(self::$kernel);
        $container = self::getContainer();
        $container->get(MailerInterface::class)->send($container->get(TriggeredEmail::class), ['recipients' => self::RECIPIENTS]);

        return $container->get(EmailQueueRepository::class);
    }

    /**
     * @param list<EmailQueue> $rows
     * @return list<?int>
     */
    private function ids(array $rows): array
    {
        return array_map(static fn(EmailQueue $row): ?int => $row->getId(), $rows);
    }
}
