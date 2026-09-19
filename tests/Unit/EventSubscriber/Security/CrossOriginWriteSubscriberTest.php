<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber\Security;

use App\EventSubscriber\Security\CrossOriginWriteSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class CrossOriginWriteSubscriberTest extends TestCase
{
    private const string HOST = 'https://admin.example.org';

    public function testRefusesAWriteCarryingASiblingHostOrigin(): void
    {
        // Arrange
        $event = $this->makeEvent('/en/admin/member/delete/7', 'POST', ['Origin' => 'https://neighbour.example.org']);

        // Act & Assert
        $this->expectException(AccessDeniedHttpException::class);
        $this->makeSubject()->onKernelController($event);
    }

    public function testRefusesAWriteCarryingAnOpaqueOrigin(): void
    {
        // Arrange
        $event = $this->makeEvent('/en/admin/member/delete/7', 'POST', ['Origin' => 'null']);

        // Act & Assert
        $this->expectException(AccessDeniedHttpException::class);
        $this->makeSubject()->onKernelController($event);
    }

    public function testAllowsAWriteFromTheAdminAreaItself(): void
    {
        // Arrange
        $event = $this->makeEvent('/en/admin/member/delete/7', 'POST', ['Origin' => self::HOST]);

        // Act & Assert
        $this->makeSubject()->onKernelController($event);
        static::assertTrue(true);
    }

    public function testAllowsAWriteThatAnnouncesNoOriginAtAll(): void
    {
        // Arrange
        $event = $this->makeEvent('/en/admin/member/delete/7', 'POST');

        // Act & Assert
        $this->makeSubject()->onKernelController($event);
        static::assertTrue(true);
    }

    #[DataProvider('fetchSiteProvider')]
    public function testFallsBackToTheFetchMetadataHeader(string $fetchSite, bool $allowed): void
    {
        // Arrange
        $event = $this->makeEvent('/en/admin/member/delete/7', 'POST', ['Sec-Fetch-Site' => $fetchSite]);

        // Assert
        if (!$allowed) {
            $this->expectException(AccessDeniedHttpException::class);
        }

        // Act
        $this->makeSubject()->onKernelController($event);
        static::assertTrue(true);
    }

    public static function fetchSiteProvider(): iterable
    {
        yield 'the admin page itself' => ['same-origin', true];
        yield 'an address typed by hand' => ['none', true];
        yield 'a sibling host' => ['same-site', false];
        yield 'an unrelated host' => ['cross-site', false];
    }

    public function testIgnoresReadsAndNonAdminWrites(): void
    {
        // Arrange
        $foreign = ['Origin' => 'https://neighbour.example.org'];
        $subject = $this->makeSubject();

        // Act & Assert
        $subject->onKernelController($this->makeEvent('/en/admin/member/delete/7', 'GET', $foreign));
        $subject->onKernelController($this->makeEvent('/en/profile/block/7', 'POST', $foreign));
        $subject->onKernelController($this->makeEvent('/api/mollie/webhook/abc', 'POST', $foreign));
        static::assertTrue(true);
    }

    public function testIgnoresASubRequest(): void
    {
        // Arrange
        $event = $this->makeEvent(
            '/en/admin/member/delete/7',
            'POST',
            ['Origin' => 'https://neighbour.example.org'],
            HttpKernelInterface::SUB_REQUEST,
        );

        // Act & Assert
        $this->makeSubject()->onKernelController($event);
        static::assertTrue(true);
    }

    /**
     * @param array<string, string> $headers
     */
    private function makeEvent(
        string $path,
        string $method,
        array $headers = [],
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): ControllerEvent {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . str_replace('-', '_', strtoupper($name))] = $value;
        }

        $request = Request::create(self::HOST . $path, $method, server: $server);
        $request->attributes->set('_locale', 'en');

        return new ControllerEvent(
            $this->createStub(HttpKernelInterface::class),
            static fn(): null => null,
            $request,
            $requestType,
        );
    }

    private function makeSubject(): CrossOriginWriteSubscriber
    {
        return new CrossOriginWriteSubscriber($this->createStub(LoggerInterface::class));
    }
}
