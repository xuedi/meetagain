<?php declare(strict_types=1);

namespace Tests\Unit\Emails;

use App\Emails\EmailAbstract;
use InvalidArgumentException;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\SendOutcome;
use PHPUnit\Framework\TestCase;

final class EmailAbstractTest extends TestCase
{
    use SampleFactoryTrait;

    public function testSendThrowsWhenTheGuardChainErrors(): void
    {
        // Arrange
        $email = $this->email(new SendOutcome(GuardResult::error('missing-user', 'no user in context')));

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Guard rule 'missing-user' for email 'test_email' returned Error: no user in context");

        // Act
        $email->send([]);
    }

    public function testSendReturnsQuietlyWhenTheGuardChainSkips(): void
    {
        // Arrange
        $email = $this->email(new SendOutcome(GuardResult::skip('opted-out', 'toggle off')));

        // Act
        $email->send([]);

        // Assert
        $this->addToAssertionCount(1);
    }

    private function email(SendOutcome $outcome): EmailAbstract
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturn($outcome);

        return new readonly class($this->createStub(BlocklistInterface::class), $this->mockSampleFactory(), $mailer) extends EmailAbstract {
            public function getIdentifier(): string
            {
                return 'test_email';
            }

            public function getTriggerLabel(): string
            {
                return 'test';
            }

            public function getDisplayMockData(string $locale): array
            {
                return ['subject' => '', 'context' => []];
            }

            public function compose(array $context): array
            {
                return [];
            }
        };
    }
}
