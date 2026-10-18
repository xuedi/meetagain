<?php declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Storage\AdapterFactory;
use InvalidArgumentException;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AdapterFactoryTest extends TestCase
{
    private const string DSN = 's3://key:secret@garage:3900/meetagain?region=garage&tls=false&web=garage:3902';

    public function testWithoutADsnEveryStorageIsADirectoryUnderTheLocalRoot(): void
    {
        // Arrange
        $factory = new AdapterFactory('', '/srv/app');

        // Act
        $described = array_map($factory->describe(...), $factory->names());

        // Assert
        static::assertFalse($factory->isRemote());
        static::assertSame(['/srv/app/data/images', '/srv/app/public/images/thumbnails', '/srv/app/data'], $described);
    }

    public function testWithADsnEveryStorageIsABucketNamedAfterThePrefix(): void
    {
        // Arrange
        $factory = new AdapterFactory(self::DSN, '/srv/app');

        // Act
        $described = array_map($factory->describe(...), $factory->names());

        // Assert
        static::assertTrue($factory->isRemote());
        static::assertSame(['s3://meetagain-images', 's3://meetagain-thumbnails', 's3://meetagain-private'], $described);
    }

    public function testARemoteStorageIsBuiltWithoutConnecting(): void
    {
        // Arrange
        $factory = new AdapterFactory(self::DSN, '/srv/app');

        // Act
        $storage = $factory->create(AdapterFactory::IMAGES);

        // Assert
        static::assertInstanceOf(FilesystemOperator::class, $storage);
    }

    public function testTheLocalCopyStaysAvailableWhileADsnIsSet(): void
    {
        // Arrange
        $root = sys_get_temp_dir() . '/adapter-factory-' . uniqid('', true);
        $factory = new AdapterFactory(self::DSN, $root);

        // Act
        $factory->createLocal(AdapterFactory::PRIVATE)->write('invoices/probe.pdf', 'pdf');

        // Assert
        static::assertFileExists($root . '/data/invoices/probe.pdf');
        unlink($root . '/data/invoices/probe.pdf');
    }

    #[DataProvider('brokenDsns')]
    public function testABrokenDsnIsRefused(string $dsn): void
    {
        // Arrange
        $factory = new AdapterFactory($dsn, '/srv/app');

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $factory->create(AdapterFactory::IMAGES);
    }

    /** @return iterable<string, array{string}> */
    public static function brokenDsns(): iterable
    {
        yield 'another scheme' => ['ftp://key:secret@garage:3900/meetagain'];
        yield 'no credentials' => ['s3://garage:3900/meetagain'];
        yield 'no bucket prefix' => ['s3://key:secret@garage:3900'];
    }

    public function testAnUnknownStorageIsRefused(): void
    {
        // Arrange
        $factory = new AdapterFactory('', '/srv/app');

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $factory->createLocal('backups');
    }
}
