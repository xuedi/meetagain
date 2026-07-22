<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Service\Config\LanguageService;
use App\Service\Media\EnabledLocalesAltRequirementProvider;
use PHPUnit\Framework\TestCase;

class EnabledLocalesAltRequirementProviderTest extends TestCase
{
    public function testReturnsUnfilteredEnabledCodes(): void
    {
        // Arrange
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getEnabledCodes')->willReturn(['en', 'de', 'es']);
        $provider = new EnabledLocalesAltRequirementProvider($languageService);

        // Act
        $result = $provider->getRequiredAltLocales(new Image());

        // Assert
        static::assertSame(['en', 'de', 'es'], $result);
    }
}
