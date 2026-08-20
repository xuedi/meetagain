<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Guard\Rule;

use App\Emails\EmailGuardOutcome;
use App\Emails\Guard\Rule\SupportRequestEmailVerifiedRule;
use App\Entity\SupportRequest;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SupportRequestEmailVerifiedRuleTest extends TestCase
{
    public static function requestProvider(): iterable
    {
        yield 'confirmed address passes' => ['john@example.com', new DateTimeImmutable('2026-01-01'), EmailGuardOutcome::Pass];
        yield 'address given but never confirmed is skipped' => ['john@example.com', null, EmailGuardOutcome::Skip];
        yield 'no address at all is skipped' => [null, null, EmailGuardOutcome::Skip];
        yield 'confirmation without an address is skipped' => [null, new DateTimeImmutable('2026-01-01'), EmailGuardOutcome::Skip];
    }

    #[DataProvider('requestProvider')]
    public function testEvaluate(?string $email, ?DateTimeImmutable $verifiedAt, EmailGuardOutcome $expected): void
    {
        // Arrange
        $request = new SupportRequest();
        $request->setEmail($email);
        $request->setEmailVerifiedAt($verifiedAt);

        // Act
        $result = new SupportRequestEmailVerifiedRule()->evaluate(['request' => $request]);

        // Assert
        static::assertSame($expected, $result->outcome);
    }

    public function testEvaluateErrorsWhenTheContextCarriesNoRequest(): void
    {
        // Act
        $result = new SupportRequestEmailVerifiedRule()->evaluate([]);

        // Assert
        static::assertSame(EmailGuardOutcome::Error, $result->outcome);
        static::assertSame('request', $result->contextKey);
    }
}
