<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Service\Security\ChallengeSigner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

final class ChallengeSignerTest extends TestCase
{
    private const string SECRET = 'a-test-secret';
    private const string CONTEXT = 'app_register';

    public function testAFreshStampVerifiesAndCarriesTheIssuedDifficulty(): void
    {
        // Arrange
        $signer = $this->signer();

        // Act
        $payload = $signer->verify($signer->issue(self::CONTEXT, 18), self::CONTEXT);

        // Assert
        static::assertNotNull($payload);
        static::assertSame(18, $payload['difficulty']);
        static::assertSame(32, strlen($payload['nonce']));
    }

    public function testAStampIssuedForAnotherFormDoesNotVerify(): void
    {
        // Arrange
        $signer = $this->signer();

        // Act
        $payload = $signer->verify($signer->issue('app_contact', 18), self::CONTEXT);

        // Assert
        static::assertNull($payload);
    }

    public function testATamperedPayloadDoesNotVerify(): void
    {
        // Arrange
        $signer = $this->signer();
        $stamp = $signer->issue(self::CONTEXT, 18);
        [$encoded, $signature] = explode('.', $stamp, 2);

        // Act
        $payload = $signer->verify(substr($encoded, 0, -1) . 'X.' . $signature, self::CONTEXT);

        // Assert
        static::assertNull($payload);
    }

    public function testAStampSignedWithAnotherSecretDoesNotVerify(): void
    {
        // Arrange
        $foreign = new ChallengeSigner('another-secret', new ArrayAdapter(), new MockClock('2026-09-18 10:00:00'));

        // Act
        $payload = $this->signer()->verify($foreign->issue(self::CONTEXT, 18), self::CONTEXT);

        // Assert
        static::assertNull($payload);
    }

    public function testAStampOlderThanTheMaximumAgeDoesNotVerify(): void
    {
        // Arrange
        $clock = new MockClock('2026-09-18 10:00:00');
        $signer = new ChallengeSigner(self::SECRET, new ArrayAdapter(), $clock);
        $stamp = $signer->issue(self::CONTEXT, 18);

        // Act
        $clock->modify('+3 hours');
        $payload = $signer->verify($stamp, self::CONTEXT);

        // Assert
        static::assertNull($payload);
    }

    public function testAnExpiredStampIsRejectedAsExpired(): void
    {
        // Arrange
        $clock = new MockClock('2026-09-18 10:00:00');
        $signer = new ChallengeSigner(self::SECRET, new ArrayAdapter(), $clock);
        $stamp = $signer->issue(self::CONTEXT, 18);

        // Act
        $clock->modify('+3 hours');
        $reason = $signer->rejectionReason($stamp, self::CONTEXT);

        // Assert
        static::assertSame('expired_stamp', $reason);
    }

    public function testAForgedOrForeignStampIsRejectedAsInvalid(): void
    {
        // Arrange
        $signer = $this->signer();
        $foreign = new ChallengeSigner('another-secret', new ArrayAdapter(), new MockClock('2026-09-18 10:00:00'));

        // Act & Assert
        static::assertSame('invalid_stamp', $signer->rejectionReason($foreign->issue(self::CONTEXT, 18), self::CONTEXT));
        static::assertSame('invalid_stamp', $signer->rejectionReason($signer->issue('app_contact', 18), self::CONTEXT));
        static::assertSame('invalid_stamp', $signer->rejectionReason('garbage', self::CONTEXT));
    }

    public function testGarbageDoesNotVerify(): void
    {
        // Arrange
        $signer = $this->signer();

        // Act & Assert
        static::assertNull($signer->verify('', self::CONTEXT));
        static::assertNull($signer->verify('nodot', self::CONTEXT));
        static::assertNull($signer->verify('!!!.abc', self::CONTEXT));
    }

    public function testANonceBurnsExactlyOnce(): void
    {
        // Arrange
        $signer = $this->signer();

        // Act & Assert
        static::assertTrue($signer->burn('abc'));
        static::assertFalse($signer->burn('abc'));
        static::assertTrue($signer->burn('def'));
    }

    public function testAProofIsAcceptedOnlyWhenItMeetsTheDifficulty(): void
    {
        // Arrange
        $signer = $this->signer();
        $nonce = 'a3f1c9de20b7455e8102cc4d9ab6e7f1';

        // Act & Assert
        static::assertTrue($signer->isProofValid($nonce, '43820', 16));
        static::assertFalse($signer->isProofValid($nonce, '43820', 18));
        static::assertFalse($signer->isProofValid($nonce, '1', 16));
    }

    private function signer(): ChallengeSigner
    {
        return new ChallengeSigner(self::SECRET, new ArrayAdapter(), new MockClock('2026-09-18 10:00:00'));
    }
}
