<?php declare(strict_types=1);

namespace Tests\Unit\Service\Config;

use App\Entity\Config;
use App\Enum\ConfigType;
use App\Repository\ConfigRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ConfigServiceTest extends TestCase
{
    public function testSetBooleanFlipsAnExistingRow(): void
    {
        // Arrange
        $row = new Config();
        $row->setName('show_town_hall');
        $row->setType(ConfigType::Boolean);
        $row->setValue('false');
        $repo = $this->createStub(ConfigRepository::class);
        $repo->method('findOneBy')->willReturn($row);

        // Act
        $this->buildService($repo)->setBoolean('show_town_hall', true);

        // Assert
        static::assertSame('true', $row->getValue());
    }

    public function testSetBooleanCreatesAMissingRowAsBoolean(): void
    {
        // Arrange
        $persisted = null;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted = $entity;
        });

        // Act
        $this->buildService(em: $em)->setBoolean('send_upcoming_digest', false);

        // Assert
        static::assertInstanceOf(Config::class, $persisted);
        static::assertSame('send_upcoming_digest', $persisted->getName());
        static::assertSame(ConfigType::Boolean, $persisted->getType());
        static::assertSame('false', $persisted->getValue());
    }

    private function buildService(?ConfigRepository $repo = null, ?EntityManagerInterface $em = null): ConfigService
    {
        return new ConfigService(
            repo: $repo ?? $this->createStub(ConfigRepository::class),
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            cache: new ArrayAdapter(),
            appState: $this->createStub(AppStateService::class),
        );
    }
}
