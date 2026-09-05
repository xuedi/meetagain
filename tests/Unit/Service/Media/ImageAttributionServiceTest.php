<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Filter\Attribution\ImageAttributionFilterService;
use App\Repository\ImageRepository;
use App\Service\Media\ImageAttributionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ImageAttributionServiceTest extends TestCase
{
    public function testVisibleAttributedImagesPassesFilterResultToRepository(): void
    {
        // Arrange
        $image = new Image();
        $filterService = $this->createStub(ImageAttributionFilterService::class);
        $filterService->method('getVisibleImageIdFilter')->willReturn([1, 2]);

        $repository = $this->createMock(ImageRepository::class);
        $repository->expects($this->once())->method('findAttributed')->with([1, 2])->willReturn([$image]);

        $service = $this->service($repository, $filterService);

        // Act
        $result = $service->getVisibleAttributedImages();

        // Assert
        static::assertSame([$image], $result);
    }

    public function testHasAnyDelegatesToRepositoryWithFilter(): void
    {
        // Arrange
        $filterService = $this->createStub(ImageAttributionFilterService::class);
        $filterService->method('getVisibleImageIdFilter')->willReturn(null);

        $repository = $this->createMock(ImageRepository::class);
        $repository->expects($this->once())->method('hasAttributed')->with(null)->willReturn(true);

        $service = $this->service($repository, $filterService);

        // Act
        $result = $service->hasAny();

        // Assert
        static::assertTrue($result);
    }

    public function testTheFilterChainRunsOnceEvenWhenBothReadersAreCalled(): void
    {
        // Arrange
        $filterService = $this->createMock(ImageAttributionFilterService::class);
        $filterService->expects($this->once())->method('getVisibleImageIdFilter')->willReturn([1, 2]);

        $repository = $this->createStub(ImageRepository::class);
        $repository->method('hasAttributed')->willReturn(true);
        $repository->method('findAttributed')->willReturn([]);

        $service = $this->service($repository, $filterService);

        // Act
        $service->hasAny();
        $service->hasAny();
        $service->getVisibleAttributedImages();

        // Assert
        static::assertTrue($service->hasAny());
    }

    public function testTheAnswerIsAskedOfTheDatabaseOnlyOncePerRequest(): void
    {
        // Arrange
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects($this->once())->method('hasAttributed')->willReturn(true);

        $filterService = $this->createStub(ImageAttributionFilterService::class);
        $filterService->method('getVisibleImageIdFilter')->willReturn([1, 2]);

        $service = $this->service($repository, $filterService);

        // Act
        $first = $service->hasAny();
        $second = $service->hasAny();

        // Assert
        static::assertTrue($first);
        static::assertTrue($second);
    }

    public function testASecondRequestOnTheSameHostReadsTheCachedAnswer(): void
    {
        // Arrange
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects($this->once())->method('hasAttributed')->willReturn(true);

        $filterService = $this->createStub(ImageAttributionFilterService::class);
        $filterService->method('getVisibleImageIdFilter')->willReturn([1, 2]);

        $cache = $this->cache();
        $stack = $this->stack('club.example.org');

        // Act
        $first = $this->service($repository, $filterService, $cache, $stack)->hasAny();
        $second = $this->service($repository, $filterService, $cache, $stack)->hasAny();

        // Assert
        static::assertTrue($first);
        static::assertTrue($second);
    }

    public function testAnotherHostGetsItsOwnAnswer(): void
    {
        // Arrange
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects($this->exactly(2))->method('hasAttributed')->willReturnOnConsecutiveCalls(true, false);

        $filterService = $this->createStub(ImageAttributionFilterService::class);
        $filterService->method('getVisibleImageIdFilter')->willReturn([1, 2]);

        $cache = $this->cache();

        // Act
        $onClub = $this->service($repository, $filterService, $cache, $this->stack('club.example.org'))->hasAny();
        $onOther = $this->service($repository, $filterService, $cache, $this->stack('other.example.org'))->hasAny();

        // Assert
        static::assertTrue($onClub);
        static::assertFalse($onOther);
    }

    public function testInvalidatingMakesTheNextRequestAskAgain(): void
    {
        // Arrange
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects($this->exactly(2))->method('hasAttributed')->willReturnOnConsecutiveCalls(false, true);

        $filterService = $this->createStub(ImageAttributionFilterService::class);
        $filterService->method('getVisibleImageIdFilter')->willReturn([1, 2]);

        $cache = $this->cache();
        $stack = $this->stack('club.example.org');

        $before = $this->service($repository, $filterService, $cache, $stack);
        $before->hasAny();

        // Act
        $before->invalidate();
        $after = $this->service($repository, $filterService, $cache, $stack)->hasAny();

        // Assert
        static::assertTrue($after);
    }

    private function service(
        ImageRepository $repository,
        ImageAttributionFilterService $filterService,
        ?TagAwareCacheInterface $cache = null,
        ?RequestStack $stack = null,
    ): ImageAttributionService {
        return new ImageAttributionService(
            $repository,
            $filterService,
            $cache ?? $this->cache(),
            $stack ?? $this->stack('club.example.org'),
        );
    }

    private function cache(): TagAwareCacheInterface
    {
        return new TagAwareAdapter(new ArrayAdapter());
    }

    private function stack(string $host): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(Request::create('https://' . $host . '/en/login'));

        return $stack;
    }
}
