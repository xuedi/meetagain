<?php declare(strict_types=1);

namespace App\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

class TrustedProxyHeadersTest extends TestCase
{
    private const array HEADER_BITS = [
        'x-forwarded-for' => Request::HEADER_X_FORWARDED_FOR,
        'x-forwarded-host' => Request::HEADER_X_FORWARDED_HOST,
        'x-forwarded-proto' => Request::HEADER_X_FORWARDED_PROTO,
        'x-forwarded-port' => Request::HEADER_X_FORWARDED_PORT,
        'x-forwarded-prefix' => Request::HEADER_X_FORWARDED_PREFIX,
        'forwarded' => Request::HEADER_FORWARDED,
    ];

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], -1);
    }

    public function testAClientSentForwardedPortNeverReachesGeneratedUrls(): void
    {
        // Arrange
        $config = Yaml::parseFile(__DIR__ . '/../../../config/packages/framework.yaml');
        $headers = $config['when@prod']['framework']['trusted_headers'];
        Request::setTrustedProxies(['10.0.0.1'], array_sum(array_map(static fn(string $name): int => self::HEADER_BITS[$name], $headers)));
        $request = Request::create('http://meetagain.org/en/events', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $request->headers->set('X-Forwarded-Proto', 'https');
        $request->headers->set('X-Forwarded-Port', '8443');

        // Act
        $origin = $request->getSchemeAndHttpHost();

        // Assert
        static::assertSame('https://meetagain.org', $origin);
    }
}
