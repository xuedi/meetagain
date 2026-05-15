<?php declare(strict_types=1);

namespace Tests\Unit\Service\Http;

use App\Service\Config\ConfigService;
use App\Service\Http\RequestHostResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RequestHostResolverTest extends TestCase
{
    public function testGetSchemeAndHostReturnsRequestValueWhenRequestIsActive(): void
    {
        // Arrange
        $request = Request::create('https://dragondescendants.example.com/en/register');
        $stack = new RequestStack();
        $stack->push($request);

        $config = $this->createStub(ConfigService::class);
        $config->method('getHost')->willReturn('https://platform.example.com');
        $config->method('getUrl')->willReturn('platform.example.com');

        $resolver = new RequestHostResolver($stack, $config);

        // Act
        $scheme = $resolver->getSchemeAndHost();
        $host = $resolver->getHost();

        // Assert
        static::assertSame('https://dragondescendants.example.com', $scheme);
        static::assertSame('dragondescendants.example.com', $host);
    }

    public function testGetSchemeAndHostFallsBackToConfigWhenNoRequestIsActive(): void
    {
        // Arrange
        $stack = new RequestStack();

        $config = $this->createStub(ConfigService::class);
        $config->method('getHost')->willReturn('https://platform.example.com');
        $config->method('getUrl')->willReturn('platform.example.com');

        $resolver = new RequestHostResolver($stack, $config);

        // Act
        $scheme = $resolver->getSchemeAndHost();
        $host = $resolver->getHost();

        // Assert
        static::assertSame('https://platform.example.com', $scheme);
        static::assertSame('platform.example.com', $host);
    }

    public function testGetSchemeAndHostStripsTrailingSlashFromConfigFallback(): void
    {
        // Arrange
        $stack = new RequestStack();

        $config = $this->createStub(ConfigService::class);
        $config->method('getHost')->willReturn('https://platform.example.com/');
        $config->method('getUrl')->willReturn('platform.example.com');

        $resolver = new RequestHostResolver($stack, $config);

        // Act
        $result = $resolver->getSchemeAndHost();

        // Assert
        static::assertSame('https://platform.example.com', $result);
    }
}
