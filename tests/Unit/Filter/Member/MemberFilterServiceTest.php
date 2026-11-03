<?php declare(strict_types=1);

namespace App\Tests\Unit\Filter\Member;

use App\Filter\Member\MemberFilterInterface;
use App\Filter\Member\MemberFilterService;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MemberFilterServiceTest extends TestCase
{
    /**
     * @param list<bool|null> $answers
     */
    #[DataProvider('directoryAnswers')]
    public function testIsDirectoryAvailable(array $answers, bool $expected): void
    {
        // Arrange
        $filters = [];
        foreach ($answers as $answer) {
            $filter = $this->createStub(MemberFilterInterface::class);
            $filter->method('isDirectoryAvailable')->willReturn($answer);
            $filters[] = $filter;
        }
        $service = new MemberFilterService($filters);

        // Act
        $result = $service->isDirectoryAvailable();

        // Assert
        static::assertSame($expected, $result);
    }

    public static function directoryAnswers(): Generator
    {
        yield 'no filters keep the directory' => [[], true];
        yield 'no opinion keeps the directory' => [[null, null], true];
        yield 'one false hides the directory' => [[null, false, true], false];
    }
}
