<?php declare(strict_types=1);

namespace Tests\Unit\Service\Seo;

use App\Publisher\Noindex\NoindexProviderInterface;
use App\Service\Seo\NoindexService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class NoindexServiceTest extends TestCase
{
    public function testIndexesWhenNoProviderIsRegistered(): void
    {
        // Arrange
        $subject = new NoindexService([]);

        // Act
        $result = $subject->shouldNoindex(new Request());

        // Assert
        static::assertFalse($result);
    }

    public function testIndexesWhenEveryProviderDefers(): void
    {
        // Arrange
        $subject = new NoindexService([$this->provider(false), $this->provider(false)]);

        // Act
        $result = $subject->shouldNoindex(new Request());

        // Assert
        static::assertFalse($result);
    }

    public function testASingleVetoKeepsThePageOutOfTheIndex(): void
    {
        // Arrange
        $subject = new NoindexService([$this->provider(false), $this->provider(true)]);

        // Act
        $result = $subject->shouldNoindex(new Request());

        // Assert
        static::assertTrue($result);
    }

    public function testStopsAtTheFirstVeto(): void
    {
        // Arrange
        $laterProvider = $this->createMock(NoindexProviderInterface::class);
        $laterProvider->expects(self::never())->method('shouldNoindex');
        $subject = new NoindexService([$this->provider(true), $laterProvider]);

        // Act
        $result = $subject->shouldNoindex(new Request());

        // Assert
        static::assertTrue($result);
    }

    private function provider(bool $verdict): NoindexProviderInterface
    {
        $provider = $this->createStub(NoindexProviderInterface::class);
        $provider->method('shouldNoindex')->willReturn($verdict);

        return $provider;
    }
}
