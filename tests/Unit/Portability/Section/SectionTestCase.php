<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\Image;
use App\Entity\User;
use App\Portability\ImageImporter;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

abstract class SectionTestCase extends TestCase
{
    /** @var list<object> */
    protected array $persisted = [];

    /**
     * @param list<object> $found
     */
    protected function entityManager(array $found = [], ?object $foundOne = null): EntityManagerInterface
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn($found);
        $repository->method('findOneBy')->willReturn($foundOne);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        return $em;
    }

    protected function context(?Image $importedImage = null): ImportContext
    {
        $imageImporter = $this->createStub(ImageImporter::class);
        $imageImporter->method('import')->willReturn($importedImage);

        return new ImportContext($imageImporter, '/archive', new User());
    }

    protected function images(): ImageWriterInterface
    {
        $images = $this->createStub(ImageWriterInterface::class);
        $images->method('addImage')->willReturnCallback(static fn(Image $image): string => 'images/' . $image->getHash() . '.jpg');

        return $images;
    }

    /**
     * @template T of object
     * @param T $entity
     * @return T
     */
    protected function withId(object $entity, int $id): object
    {
        new ReflectionProperty($entity::class, 'id')->setValue($entity, $id);

        return $entity;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    protected function onlyPersisted(string $class): object
    {
        $matches = array_values(array_filter($this->persisted, static fn(object $entity): bool => $entity instanceof $class));
        static::assertCount(1, $matches);

        return $matches[0];
    }
}
