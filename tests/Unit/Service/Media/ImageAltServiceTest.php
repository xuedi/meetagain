<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Service\Config\LanguageService;
use App\Service\Media\AltLocaleRequirementResolver;
use App\Service\Media\ImageAltService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ImageAltServiceTest extends TestCase
{
    public function testApplyAltWritesSourceLocaleToBaseAltAndOthersToTranslations(): void
    {
        // Arrange
        $image = self::imageWithId(1);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');
        $service = $this->service(requiredLocales: ['en', 'de'], entityManager: $entityManager);

        // Act
        $service->applyAlt($image, ['en' => ' english alt ', 'de' => 'deutscher alt']);

        // Assert
        static::assertSame('english alt', $image->getAlt());
        static::assertSame('deutscher alt', $image->getAltTranslation('de'));
        static::assertNotNull($image->getUpdatedAt());
    }

    public function testApplyAltEmptyStringUnsets(): void
    {
        // Arrange
        $image = self::imageWithId(1);
        $image->setAlt('old english');
        $image->setAltTranslation('de', 'alter alt');
        $service = $this->service(requiredLocales: ['en', 'de']);

        // Act
        $service->applyAlt($image, ['en' => '', 'de' => '  ']);

        // Assert
        static::assertNull($image->getAlt());
        static::assertNull($image->getAltTranslation('de'));
    }

    public function testApplyAltCapsTextAt255Characters(): void
    {
        // Arrange
        $image = self::imageWithId(1);
        $service = $this->service(requiredLocales: ['en']);

        // Act
        $service->applyAlt($image, ['en' => str_repeat('a', 300)]);

        // Assert
        static::assertSame(str_repeat('a', 255), $image->getAlt());
    }

    public function testApplyAltThrowsOnLocaleOutsideRequiredSetWithoutWriting(): void
    {
        // Arrange
        $image = self::imageWithId(1);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');
        $service = $this->service(requiredLocales: ['en', 'de'], entityManager: $entityManager);

        // Act & Assert - validation happens before any mutation, so 'de' is not applied either.
        $this->expectException(InvalidArgumentException::class);
        $service->applyAlt($image, ['de' => 'deutscher alt', 'fr' => 'texte']);
    }

    public function testFindMissingAltPageFiltersCompleteImagesAndReturnsCursorOnFullPage(): void
    {
        // Arrange - a full page of two candidates; image 1 is complete, image 2 misses 'de'.
        $complete = self::imageWithId(1);
        $complete->setAlt('english');
        $complete->setAltTranslation('de', 'deutsch');
        $incomplete = self::imageWithId(2);
        $incomplete->setAlt('english');

        $service = $this->service(
            requiredLocales: ['en', 'de'],
            candidates: [$complete, $incomplete],
        );

        // Act
        $page = $service->findMissingAltPage(null, 2);

        // Assert
        static::assertCount(1, $page['items']);
        static::assertSame($incomplete, $page['items'][0]['image']);
        static::assertSame(['en', 'de'], $page['items'][0]['requiredLocales']);
        static::assertSame(['de'], $page['items'][0]['missingLocales']);
        static::assertSame(2, $page['nextAfterId']);
    }

    public function testFindMissingAltPageEndsCursorOnPartialPage(): void
    {
        // Arrange - fewer candidates than the limit means the scan reached the end.
        $incomplete = self::imageWithId(5);
        $service = $this->service(requiredLocales: ['en'], candidates: [$incomplete]);

        // Act
        $page = $service->findMissingAltPage(null, 10);

        // Assert
        static::assertCount(1, $page['items']);
        static::assertNull($page['nextAfterId']);
    }

    public function testFindMissingAltPageEmptyScan(): void
    {
        // Arrange
        $service = $this->service(requiredLocales: [], candidates: []);

        // Act & Assert
        static::assertSame(['items' => [], 'nextAfterId' => null], $service->findMissingAltPage(99, 10));
    }

    /**
     * @param list<string> $requiredLocales
     * @param list<Image> $candidates
     */
    private function service(
        array $requiredLocales,
        array $candidates = [],
        ?EntityManagerInterface $entityManager = null,
    ): ImageAltService {
        $repository = $this->createStub(ImageRepository::class);
        $repository->method('findAuditCandidates')->willReturn($candidates);

        $language = $this->createStub(LanguageService::class);
        $language->method('getFilteredDefaultLocale')->willReturn('en');

        $requirements = $this->createStub(AltLocaleRequirementResolver::class);
        $requirements->method('getRequiredAltLocales')->willReturn($requiredLocales);
        $requirements->method('getRequiredAltLocalesForImages')->willReturnCallback(
            static function (array $images) use ($requiredLocales): array {
                $result = [];
                foreach ($images as $image) {
                    $result[(int) $image->getId()] = $requiredLocales;
                }

                return $result;
            },
        );

        return new ImageAltService(
            $repository,
            $language,
            $requirements,
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
        );
    }

    private static function imageWithId(int $id): Image
    {
        $image = new Image();
        $property = new ReflectionProperty(Image::class, 'id');
        $property->setValue($image, $id);

        return $image;
    }
}
