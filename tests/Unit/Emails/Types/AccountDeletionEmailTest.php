<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\Types\AccountDeletionEmail;
use App\Entity\User;
use App\Enum\EmailType;
use App\Enum\UserStatus;
use App\Service\Config\ConfigService;
use App\Service\Member\Erasure;
use DateTimeImmutable;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Tests\Unit\Emails\SampleFactoryTrait;
use Tests\Unit\Stubs\UserStub;

class AccountDeletionEmailTest extends TestCase
{
    use SampleFactoryTrait;

    public function testComposesTheErasureDateFromTheDeletionStamp(): void
    {
        // Arrange
        $requestedAt = new DateTimeImmutable('2026-11-22 15:30:00');
        $user = $this->deletedUser()->setDeletionRequestedAt($requestedAt);
        $emailType = $this->createEmailType();

        // Act
        $messages = $emailType->compose(['user' => $user]);

        // Assert
        static::assertCount(1, $messages);
        $context = $messages[0]->getContext();
        static::assertSame(['username', 'erasureDate', 'lang'], array_keys($context));
        static::assertSame($requestedAt->modify(sprintf('+%d days', Erasure::ERASURE_WINDOW_DAYS))->format('Y-m-d'), $context['erasureDate']);
        static::assertSame('Orlando', $context['username']);
        static::assertSame('de', $context['lang']);
        static::assertSame('orlando@example.org', $messages[0]->getTo()[0]->getAddress());
    }

    public function testAnAccountWithoutADeletionStampGetsNoMail(): void
    {
        // Arrange
        $emailType = $this->createEmailType();

        // Act
        $messages = $emailType->compose(['user' => $this->deletedUser()]);

        // Assert
        static::assertSame([], $messages);
    }

    public function testADeletedMemberPassesTheGuardRules(): void
    {
        // Arrange
        $user = $this->deletedUser()->setDeletionRequestedAt(new DateTimeImmutable('2026-11-22'));
        $emailType = $this->createEmailType();

        // Act
        $passes = $emailType->guardCheck(['user' => $user]);

        // Assert
        static::assertTrue($passes);
    }

    public function testABlockedAddressIsSkipped(): void
    {
        // Arrange
        $blocklist = $this->createStub(BlocklistInterface::class);
        $blocklist->method('isBlocked')->willReturn(true);
        $emailType = $this->createEmailType($blocklist);

        // Act
        $passes = $emailType->guardCheck(['user' => $this->deletedUser()]);

        // Assert
        static::assertFalse($passes);
    }

    public function testIdentifier(): void
    {
        // Arrange
        $emailType = $this->createEmailType();

        // Act & Assert
        static::assertSame(EmailType::AccountDeletion->value, $emailType->getIdentifier());
    }

    private function deletedUser(): User
    {
        return new UserStub()
            ->setId(2)
            ->setName('Orlando')
            ->setEmail('orlando@example.org')
            ->setLocale('de')
            ->setStatus(UserStatus::Deleted);
    }

    private function createEmailType(?BlocklistInterface $blocklist = null): AccountDeletionEmail
    {
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@platform.example.com'));

        return new AccountDeletionEmail(
            $blocklist ?? $this->createStub(BlocklistInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(MailerInterface::class),
            $config,
        );
    }
}
