<?php declare(strict_types=1);

namespace Tests\Unit\Publisher\PluginSettings;

use App\Entity\PluginSettings;
use App\Publisher\PluginSettings\GenericPluginSettingsStore;
use App\Repository\PluginSettingsRepository;
use App\Service\Admin\PluginSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Publisher\PluginSettings\Fixtures\StubDescriptor;
use Tests\Unit\Publisher\PluginSettings\Fixtures\StubSettingsData;

class GenericPluginSettingsStoreTest extends TestCase
{
    public function testSupportsOnlyGlobalScope(): void
    {
        // Arrange
        $store = $this->store($this->createStub(PluginSettingsRepository::class), $this->createStub(EntityManagerInterface::class));

        // Act + Assert
        static::assertTrue($store->supports('stub', null));
        static::assertFalse($store->supports('stub', '7'));
    }

    public function testLoadReturnsNullWhenNothingStored(): void
    {
        // Arrange
        $repo = $this->createStub(PluginSettingsRepository::class);
        $repo->method('findOneByPluginKey')->willReturn(null);
        $store = $this->store($repo, $this->createStub(EntityManagerInterface::class));

        // Act + Assert
        static::assertNull($store->load('stub', null));
    }

    public function testLoadHydratesDtoViaDescriptorClass(): void
    {
        // Arrange
        $row = new PluginSettings()
            ->setPluginKey('stub')
            ->setData(['label' => 'stored', 'count' => 3]);
        $repo = $this->createStub(PluginSettingsRepository::class);
        $repo->method('findOneByPluginKey')->willReturn($row);
        $store = $this->store($repo, $this->createStub(EntityManagerInterface::class));

        // Act
        $result = $store->load('stub', null);

        // Assert
        static::assertInstanceOf(StubSettingsData::class, $result);
        static::assertSame('stored', $result->label);
        static::assertSame(3, $result->count);
    }

    public function testSaveSerializesDtoToRow(): void
    {
        // Arrange
        $repo = $this->createStub(PluginSettingsRepository::class);
        $repo->method('findOneByPluginKey')->willReturn(null);

        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em
            ->expects(static::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted = $entity;
            });
        $em->expects(static::once())->method('flush');

        $store = $this->store($repo, $em);

        // Act
        $store->save('stub', new StubSettingsData('written', 9), null);

        // Assert
        static::assertInstanceOf(PluginSettings::class, $persisted);
        static::assertSame('stub', $persisted->getPluginKey());
        static::assertSame(['label' => 'written', 'count' => 9], $persisted->getData());
    }

    private function store(PluginSettingsRepository $repo, EntityManagerInterface $em): GenericPluginSettingsStore
    {
        return new GenericPluginSettingsStore($repo, $em, new PluginSettingsService([new StubDescriptor('stub')]));
    }
}
