<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Service\Security\RequestIdentityResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class RequestIdentityResolverTest extends TestCase
{
    private const string IP = '203.0.113.5';

    public function testACookieValuedLikeAnIpKeyDoesNotResolveToThatIpsKey(): void
    {
        // Arrange
        $resolver = new RequestIdentityResolver(new NullLogger());
        $forged = $this->request(['MOCKSESSID' => 'ip:' . self::IP]);
        $cookieless = $this->request([]);

        // Act
        $forgedKey = $resolver->resolveSessionKey($forged, self::IP);
        $cookielessKey = $resolver->resolveSessionKey($cookieless, self::IP);

        // Assert
        static::assertSame('ip:' . self::IP, $cookielessKey);
        static::assertSame('ip:' . self::IP, $forgedKey, 'a malformed cookie counts as no cookie');
        static::assertNotSame('ip:' . self::IP, $resolver->resolveSessionKey($this->request(['MOCKSESSID' => 'abcdefghijklmnopqrstuv']), self::IP));
    }

    public function testAValidCookieResolvesToItsOwnHashedNamespace(): void
    {
        // Arrange
        $resolver = new RequestIdentityResolver(new NullLogger());
        $cookie = 'abcdefghijklmnopqrstuvwxyz012345';

        // Act
        $key = $resolver->resolveSessionKey($this->request(['MOCKSESSID' => $cookie]), self::IP);

        // Assert
        static::assertSame('s:' . hash('sha256', $cookie), $key);
    }

    /** @return iterable<string, array{array<string, string>, bool}> */
    public static function originProvider(): iterable
    {
        yield 'no headers' => [[], false];
        yield 'same origin fetch' => [['Sec-Fetch-Site' => 'same-origin'], false];
        yield 'direct navigation' => [['Sec-Fetch-Site' => 'none'], false];
        yield 'cross-site fetch' => [['Sec-Fetch-Site' => 'cross-site'], true];
        yield 'same-site fetch' => [['Sec-Fetch-Site' => 'same-site'], true];
        yield 'foreign origin' => [['Origin' => 'https://evil.example'], true];
        yield 'opaque origin' => [['Origin' => 'null'], true];
        yield 'own origin' => [['Origin' => 'http://localhost'], false];
    }

    /** @param array<string, string> $headers */
    #[DataProvider('originProvider')]
    public function testItRecognisesACrossSiteRequest(array $headers, bool $expected): void
    {
        // Arrange
        $request = Request::create('http://localhost/x');
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        // Act
        $crossSite = new RequestIdentityResolver(new NullLogger())->isCrossSite($request);

        // Assert
        static::assertSame($expected, $crossSite);
    }

    /** @param array<string, string> $cookies */
    private function request(array $cookies): Request
    {
        $request = Request::create('/', cookies: $cookies);
        $request->setSession(new Session(new MockArraySessionStorage(name: 'MOCKSESSID')));

        return $request;
    }
}
