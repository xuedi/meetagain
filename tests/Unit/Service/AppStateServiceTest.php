<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\AppState;
use App\Repository\AppStateRepository;
use App\Service\AppStateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class AppStateServiceTest extends TestCase
{
    private AppStateRepository&Stub $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(AppStateRepository::class);
    }

    private function makeService(?EntityManagerInterface $em = null): AppStateService
    {
        return new AppStateService($this->repository, $em ?? $this->createStub(EntityManagerInterface::class));
    }

    public function testGetReturnsNullWhenNoRowExists(): void
    {
        // Arrange
        $this->repository->method('findByKey')->willReturn(null);

        // Act
        $result = $this->makeService()->get('some_key');

        // Assert
        static::assertNull($result);
    }

    public function testGetReturnsValueWhenRowExists(): void
    {
        // Arrange
        $entry = new AppState('some_key', 'stored_value', new DateTimeImmutable());
        $this->repository->method('findByKey')->willReturn($entry);

        // Act
        $result = $this->makeService()->get('some_key');

        // Assert
        static::assertSame('stored_value', $result);
    }

    public function testSetCreatesNewRowWhenKeyDoesNotExist(): void
    {
        // Arrange
        $this->repository->method('findByKey')->willReturn(null);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')
            ->with(static::callback(static function (AppState $state): bool {
                return $state->getKeyName() === 'new_key' && $state->getValue() === 'new_value';
            }));
        $em->expects($this->once())->method('flush');

        // Act
        $this->makeService($em)->set('new_key', 'new_value');
    }

    public function testSetUpdatesExistingRowWhenKeyExists(): void
    {
        // Arrange
        $existing = new AppState('existing_key', 'old_value', new DateTimeImmutable('2026-01-01'));
        $this->repository->method('findByKey')->willReturn($existing);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');

        // Act
        $this->makeService($em)->set('existing_key', 'updated_value');

        // Assert: value was mutated on the existing entity
        static::assertSame('updated_value', $existing->getValue());
    }
}
