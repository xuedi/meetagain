<?php declare(strict_types=1);

namespace Tests\Unit\Service\Command;

use App\Service\Command\RebuildThemeCommand;
use PHPUnit\Framework\TestCase;

class RebuildThemeCommandTest extends TestCase
{
    public function testGetCommandReturnsSassBuild(): void
    {
        static::assertSame('sass:build', new RebuildThemeCommand()->getCommand());
    }

    public function testGetParameterIncludesTheCommandName(): void
    {
        static::assertSame(['command' => 'sass:build'], new RebuildThemeCommand()->getParameter());
    }
}
