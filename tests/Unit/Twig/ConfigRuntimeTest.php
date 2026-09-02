<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Service\Config\ConfigService;
use App\Service\Media\ImageAttributionService;
use App\Service\Media\SiteLogoResolver;
use App\Twig\ConfigRuntime;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ConfigRuntimeTest extends TestCase
{
    private Stub&ConfigService $configServiceStub;
    private Stub&SiteLogoResolver $siteLogoResolverStub;
    private Stub&ImageAttributionService $imageAttributionServiceStub;
    private ConfigRuntime $subject;

    protected function setUp(): void
    {
        $this->configServiceStub = $this->createStub(ConfigService::class);
        $this->siteLogoResolverStub = $this->createStub(SiteLogoResolver::class);
        $this->imageAttributionServiceStub = $this->createStub(ImageAttributionService::class);
        $this->subject = new ConfigRuntime(
            $this->configServiceStub,
            $this->siteLogoResolverStub,
            $this->imageAttributionServiceStub,
        );
    }

    public function testHasImageAttributionsDelegatesToService(): void
    {
        $this->imageAttributionServiceStub->method('hasAny')->willReturn(true);

        static::assertTrue($this->subject->hasImageAttributions());
    }

    public function testGetDateFormatDelegatesToConfigService(): void
    {
        $this->configServiceStub->method('getDateFormat')->willReturn('d.m.Y H:i');

        static::assertSame('d.m.Y H:i', $this->subject->getDateFormat());
    }

    public function testGetDateFormatFlatpickrDelegatesToConfigService(): void
    {
        $this->configServiceStub->method('getDateFormatFlatpickr')->willReturn('d.m.Y H:i');

        static::assertSame('d.m.Y H:i', $this->subject->getDateFormatFlatpickr());
    }

    public function testSiteLogoDelegatesToResolver(): void
    {
        $logo = ['url' => '/media/logo.png', 'width' => 200, 'height' => 60];
        $this->siteLogoResolverStub->method('resolve')->willReturn($logo);

        static::assertSame($logo, $this->subject->siteLogo());
    }
}
