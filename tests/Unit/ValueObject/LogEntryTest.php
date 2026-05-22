<?php declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use App\ValueObject\LogEntry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LogEntryTest extends TestCase
{
    /**
     * @param array{date: string, type: string, level: string, message: string, json: ?string} $expected
     */
    #[DataProvider('provideParseCases')]
    public function testFromStringParsesLine(string $line, array $expected): void
    {
        // Act
        $entry = LogEntry::fromString($line);

        // Assert
        static::assertSame($expected['date'], $entry->getDate()->format('Y-m-d H:i:s'));
        static::assertSame($expected['type'], $entry->getType());
        static::assertSame($expected['level'], $entry->getLevel());
        static::assertSame($expected['message'], $entry->getMessage());
        static::assertSame($expected['json'], $entry->getJson());
    }

    public static function provideParseCases(): iterable
    {
        yield 'plain message' => [
            '[2026-05-11T12:00:00+00:00] app.INFO: hello world',
            [
                'date' => '2026-05-11 12:00:00',
                'type' => 'app',
                'level' => 'INFO',
                'message' => 'hello world',
                'json' => null,
            ],
        ];
        yield 'with context object' => [
            '[2026-05-11T12:00:00+00:00] app.ERROR: kaboom {"foo":"bar"}',
            [
                'date' => '2026-05-11 12:00:00',
                'type' => 'app',
                'level' => 'ERROR',
                'message' => 'kaboom',
                'json' => '{"foo":"bar"}',
            ],
        ];
        yield 'monolog default with context and empty extra' => [
            '[2026-05-11T12:00:00+00:00] console.CRITICAL: oh no {"exception":"trace"} []',
            [
                'date' => '2026-05-11 12:00:00',
                'type' => 'console',
                'level' => 'CRITICAL',
                'message' => 'oh no',
                'json' => '{"exception":"trace"} []',
            ],
        ];
        yield 'message containing colon' => [
            '[2026-05-11T12:00:00+00:00] http.INFO: GET: /foo returned 200',
            [
                'date' => '2026-05-11 12:00:00',
                'type' => 'http',
                'level' => 'INFO',
                'message' => 'GET: /foo returned 200',
                'json' => null,
            ],
        ];
    }

    /**
     * @param list<mixed> $expected
     */
    #[DataProvider('provideContextChunkCases')]
    public function testGetContextChunksDecodesAndFilters(string $line, array $expected): void
    {
        // Arrange
        $entry = LogEntry::fromString($line);

        // Act
        $chunks = $entry->getContextChunks();

        // Assert
        static::assertSame($expected, $chunks);
    }

    public static function provideContextChunkCases(): iterable
    {
        yield 'no json tail' => [
            '[2026-05-11T12:00:00+00:00] app.INFO: hello',
            [],
        ];
        yield 'single context object' => [
            '[2026-05-11T12:00:00+00:00] app.ERROR: msg {"foo":"bar"}',
            [['foo' => 'bar']],
        ];
        yield 'context plus empty extra is one chunk' => [
            '[2026-05-11T12:00:00+00:00] app.ERROR: msg {"foo":"bar"} []',
            [['foo' => 'bar']],
        ];
        yield 'context and extra both populated' => [
            '[2026-05-11T12:00:00+00:00] app.ERROR: msg {"a":1} {"b":2}',
            [['a' => 1], ['b' => 2]],
        ];
        yield 'nested object braces are tracked' => [
            '[2026-05-11T12:00:00+00:00] app.ERROR: msg {"outer":{"inner":"v"}}',
            [['outer' => ['inner' => 'v']]],
        ];
        yield 'braces inside json string do not split' => [
            '[2026-05-11T12:00:00+00:00] app.ERROR: msg {"text":"closing brace } inside"}',
            [['text' => 'closing brace } inside']],
        ];
        yield 'escaped quotes inside string' => [
            '[2026-05-11T12:00:00+00:00] app.ERROR: msg {"text":"he said \"hi\""}',
            [['text' => 'he said "hi"']],
        ];
        yield 'both empty arrays are skipped entirely' => [
            '[2026-05-11T12:00:00+00:00] app.INFO: msg [] []',
            [],
        ];
    }

    public function testGetHashIsDeterministic(): void
    {
        // Arrange
        $line = '[2026-05-11T12:00:00+00:00] app.INFO: stable message {"k":"v"}';

        // Act
        $first = LogEntry::fromString($line)->getHash();
        $second = LogEntry::fromString($line)->getHash();

        // Assert
        static::assertSame($first, $second);
        static::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $first);
    }

    /**
     * @param array<string, string> $linesByName
     */
    #[DataProvider('provideHashDifferentiationCases')]
    public function testGetHashDifferentiatesBy(array $linesByName): void
    {
        // Act
        $hashes = [];
        foreach ($linesByName as $name => $line) {
            $hashes[$name] = LogEntry::fromString($line)->getHash();
        }

        // Assert - all hashes distinct
        static::assertCount(count($linesByName), array_unique($hashes));
    }

    public static function provideHashDifferentiationCases(): iterable
    {
        yield 'different timestamps' => [[
            'a' => '[2026-05-11T12:00:00+00:00] app.INFO: same message',
            'b' => '[2026-05-11T12:00:01+00:00] app.INFO: same message',
        ]];
        yield 'different levels' => [[
            'info' => '[2026-05-11T12:00:00+00:00] app.INFO: same message',
            'error' => '[2026-05-11T12:00:00+00:00] app.ERROR: same message',
        ]];
        yield 'different channels' => [[
            'app' => '[2026-05-11T12:00:00+00:00] app.INFO: same message',
            'console' => '[2026-05-11T12:00:00+00:00] console.INFO: same message',
        ]];
        yield 'different messages' => [[
            'first' => '[2026-05-11T12:00:00+00:00] app.INFO: hello',
            'second' => '[2026-05-11T12:00:00+00:00] app.INFO: world',
        ]];
        yield 'different json tails' => [[
            'a' => '[2026-05-11T12:00:00+00:00] app.INFO: msg {"k":"a"}',
            'b' => '[2026-05-11T12:00:00+00:00] app.INFO: msg {"k":"b"}',
        ]];
    }
}
