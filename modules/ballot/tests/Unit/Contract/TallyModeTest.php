<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Unit\Contract;

use Module\Ballot\Contract\TallyMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TallyModeTest extends TestCase
{
    #[DataProvider('provideModes')]
    public function testASingleChoiceBallotAllowsOneSelectionAndApprovalAllowsAny(TallyMode $mode, ?int $maximum): void
    {
        // Act
        $actual = $mode->maximumSelections();

        // Assert
        static::assertSame($maximum, $actual);
    }

    /**
     * @return iterable<string, array{TallyMode, ?int}>
     */
    public static function provideModes(): iterable
    {
        yield 'approval' => [TallyMode::Approval, null];
        yield 'single' => [TallyMode::Single, 1];
    }
}
