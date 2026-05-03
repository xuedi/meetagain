<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Publisher\OgImage\OgImageProviderInterface;
use App\Publisher\OgImage\ResolvedOgImage;
use App\Repository\ImageRepository;
use App\Service\Config\ConfigService;
use App\Service\Media\OgImageResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class OgImageResolverTest extends TestCase
{
    private ConfigService $configServiceStub;
    private ImageRepository $imageRepositoryStub;
    private RequestStack $requestStack;
    private TranslatorInterface $translatorStub;

    protected function setUp(): void
    {
        $this->configServiceStub = $this->createStub(ConfigService::class);
        $this->imageRepositoryStub = $this->createStub(ImageRepository::class);
        $this->requestStack = new RequestStack();
        $request = Request::create('https://example.test/');
        $this->requestStack->push($request);
        $this->translatorStub = $this->createStub(TranslatorInterface::class);
        $this->translatorStub->method('trans')->willReturn('Default Alt');
    }

    public function testReturnsNullWhenNoProvidersAndNoSystemImage(): void
    {
        // Arrange
        $this->configServiceStub->method('getWebsiteImageId')->willReturn(null);

        $resolver = new OgImageResolver(
            providers: [],
            configService: $this->configServiceStub,
            imageRepository: $this->imageRepositoryStub,
            requestStack: $this->requestStack,
            translator: $this->translatorStub,
        );

        // Act
        $result = $resolver->resolve();

        // Assert
        static::assertNull($result);
    }

    public function testReturnsSystemImageWhenNoProvidersClaim(): void
    {
        // Arrange
        $this->configServiceStub->method('getWebsiteImageId')->willReturn(42);
        $image = new Image();
        $image->setHash('sysHash');
        $image->setUpdatedAt(new DateTimeImmutable('2026-05-03 12:00:00'));
        $this->imageRepositoryStub->method('find')->willReturn($image);

        $resolver = new OgImageResolver(
            providers: [],
            configService: $this->configServiceStub,
            imageRepository: $this->imageRepositoryStub,
            requestStack: $this->requestStack,
            translator: $this->translatorStub,
        );

        // Act
        $result = $resolver->resolve();

        // Assert
        static::assertNotNull($result);
        static::assertSame('https://example.test/images/thumbnails/sysHash_1200x630.webp?v20260503120000', $result->absoluteUrl);
        static::assertSame(1200, $result->width);
        static::assertSame(630, $result->height);
        static::assertSame('Default Alt', $result->altText);
    }

    public function testProviderClaimWinsOverSystemDefault(): void
    {
        // Arrange
        $providerResolved = new ResolvedOgImage(
            absoluteUrl: 'https://group.test/images/thumbnails/groupHash_1200x630.webp',
            width: 1200,
            height: 630,
            altText: 'Group Name',
        );
        $provider = $this->createStub(OgImageProviderInterface::class);
        $provider->method('resolveOgImage')->willReturn($providerResolved);

        $this->configServiceStub->method('getWebsiteImageId')->willReturn(42);
        $image = new Image();
        $image->setHash('sysHash');
        $this->imageRepositoryStub->method('find')->willReturn($image);

        $resolver = new OgImageResolver(
            providers: [$provider],
            configService: $this->configServiceStub,
            imageRepository: $this->imageRepositoryStub,
            requestStack: $this->requestStack,
            translator: $this->translatorStub,
        );

        // Act
        $result = $resolver->resolve();

        // Assert
        static::assertSame($providerResolved, $result);
    }

    public function testProviderReturningNullFallsThroughToSystem(): void
    {
        // Arrange
        $provider = $this->createStub(OgImageProviderInterface::class);
        $provider->method('resolveOgImage')->willReturn(null);

        $this->configServiceStub->method('getWebsiteImageId')->willReturn(42);
        $image = new Image();
        $image->setHash('sysHash');
        $this->imageRepositoryStub->method('find')->willReturn($image);

        $resolver = new OgImageResolver(
            providers: [$provider],
            configService: $this->configServiceStub,
            imageRepository: $this->imageRepositoryStub,
            requestStack: $this->requestStack,
            translator: $this->translatorStub,
        );

        // Act
        $result = $resolver->resolve();

        // Assert
        static::assertNotNull($result);
        static::assertStringContainsString('sysHash_1200x630.webp', $result->absoluteUrl);
    }

    public function testFirstProviderClaimWinsAcrossChain(): void
    {
        // Arrange
        $firstResolved = new ResolvedOgImage(
            absoluteUrl: 'https://first.test/img.webp',
            width: 1200,
            height: 630,
            altText: 'First',
        );
        $secondResolved = new ResolvedOgImage(
            absoluteUrl: 'https://second.test/img.webp',
            width: 1200,
            height: 630,
            altText: 'Second',
        );
        $first = $this->createStub(OgImageProviderInterface::class);
        $first->method('resolveOgImage')->willReturn($firstResolved);
        $second = $this->createStub(OgImageProviderInterface::class);
        $second->method('resolveOgImage')->willReturn($secondResolved);

        $resolver = new OgImageResolver(
            providers: [$first, $second],
            configService: $this->configServiceStub,
            imageRepository: $this->imageRepositoryStub,
            requestStack: $this->requestStack,
            translator: $this->translatorStub,
        );

        // Act
        $result = $resolver->resolve();

        // Assert
        static::assertSame($firstResolved, $result);
    }

    public function testReturnsNullWhenSystemIdSetButImageMissing(): void
    {
        // Arrange
        $this->configServiceStub->method('getWebsiteImageId')->willReturn(99);
        $this->imageRepositoryStub->method('find')->willReturn(null);

        $resolver = new OgImageResolver(
            providers: [],
            configService: $this->configServiceStub,
            imageRepository: $this->imageRepositoryStub,
            requestStack: $this->requestStack,
            translator: $this->translatorStub,
        );

        // Act
        $result = $resolver->resolve();

        // Assert
        static::assertNull($result);
    }
}
