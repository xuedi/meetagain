<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Service\Security\LoadtestBypass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class LoadtestBypassTest extends TestCase
{
    #[DataProvider('provideIsActiveCases')]
    public function testIsActiveCombinesEnvironmentAndHeader(?Request $request, string $environment, bool $expected): void
    {
        static::assertSame($expected, LoadtestBypass::isActive($request, $environment));
    }

    public static function provideIsActiveCases(): iterable
    {
        $withHeader = Request::create('/', server: ['HTTP_X_LOADTEST_BYPASS' => '1']);
        $wrongValue = Request::create('/', server: ['HTTP_X_LOADTEST_BYPASS' => '0']);
        $noHeader = Request::create('/');

        yield 'prod ignores the header even when set' => [$withHeader, 'prod', false];
        yield 'null request returns false even in dev' => [null, 'dev', false];
        yield 'dev with header value 1 activates' => [$withHeader, 'dev', true];
        yield 'dev with header value 0 does not activate' => [$wrongValue, 'dev', false];
        yield 'dev without header does not activate' => [$noHeader, 'dev', false];
        yield 'test env with header activates' => [$withHeader, 'test', true];
    }

    public function testAcceptedReturnsFullyOpenRateLimit(): void
    {
        // Act
        $limit = LoadtestBypass::accepted();

        // Assert
        static::assertTrue($limit->isAccepted());
        static::assertSame(PHP_INT_MAX, $limit->getLimit());
        static::assertSame(PHP_INT_MAX, $limit->getRemainingTokens());
    }
}
