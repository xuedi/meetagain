<?php declare(strict_types=1);

namespace Tests\Unit\Anonymization\Anonymizer;

use App\Anonymization\Anonymizer\UsersAnonymizer;
use Tests\Unit\Anonymization\AnonymizerTestCase;

final class UsersAnonymizerTest extends AnonymizerTestCase
{
    public function testEveryClaimedColumnIsWritten(): void
    {
        // Arrange
        $subject = new UsersAnonymizer($this->scrubber());

        // Act
        $subject->anonymize($this->context());

        // Assert
        $this->assertEveryClaimIsActedOn($subject);
    }

    public function testOneStatementGivesEveryAccountTheSamePasswordAndAPlaceholderAddress(): void
    {
        // Arrange
        $subject = new UsersAnonymizer($this->scrubber());

        // Act
        $subject->anonymize($this->context());

        // Assert
        $withPassword = array_values(array_filter($this->statementsOn('user'), static fn(array $statement): bool => isset($statement[1]['password'])));
        self::assertCount(1, $withPassword);
        [$sql, $params] = $withPassword[0];
        self::assertSame(self::HASH, $params['password']);
        self::assertStringContainsString("CONCAT('user', `id`, '@example.invalid')", $sql);
        self::assertStringContainsString('role <> :system AND email NOT LIKE :erased', $sql);
        self::assertSame(['SYSTEM', '%@erased.invalid'], [$params['system'], $params['erased']]);
    }
}
