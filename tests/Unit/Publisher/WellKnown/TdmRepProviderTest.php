<?php declare(strict_types=1);

namespace Tests\Unit\Publisher\WellKnown;

use App\Publisher\WellKnown\TdmRepProvider;
use App\Service\Config\ConfigService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class TdmRepProviderTest extends TestCase
{
    #[DataProvider('reservationProvider')]
    public function testReservationFlagFollowsConfig(bool $enabled, int $expected): void
    {
        // Arrange
        $provider = new TdmRepProvider($this->configService($enabled, ''));

        // Act
        $document = $provider->provide(new Request());

        // Assert
        self::assertSame([['location' => '/', 'tdm-reservation' => $expected]], $this->decode($document->body ?? ''));
    }

    public static function reservationProvider(): iterable
    {
        yield 'rights reserved' => [true, 1];
        yield 'rights not reserved' => [false, 0];
    }

    public function testPolicyUrlIsOmittedWhenUnset(): void
    {
        // Arrange
        $provider = new TdmRepProvider($this->configService(true, ''));

        // Act
        $document = $provider->provide(new Request());

        // Assert
        self::assertArrayNotHasKey('tdm-policy', $this->decode($document->body ?? '')[0]);
    }

    public function testPolicyUrlIsIncludedWhenSet(): void
    {
        // Arrange
        $provider = new TdmRepProvider($this->configService(true, 'https://example.org/tdm-policy'));

        // Act
        $document = $provider->provide(new Request());

        // Assert
        self::assertSame('https://example.org/tdm-policy', $this->decode($document->body ?? '')[0]['tdm-policy']);
    }

    public function testDocumentIsServedAsJson(): void
    {
        // Arrange
        $provider = new TdmRepProvider($this->configService(true, ''));

        // Act
        $document = $provider->provide(new Request());

        // Assert
        self::assertSame('application/json', $document?->contentType);
    }

    private function configService(bool $reservation, string $policyUrl): ConfigService
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('isTdmReservationEnabled')->willReturn($reservation);
        $configService->method('getTdmPolicyUrl')->willReturn($policyUrl);

        return $configService;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decode(string $body): array
    {
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}
