<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\StorageCopyCommand;
use App\Storage\AdapterFactory;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class StorageCopyCommandTest extends TestCase
{
    /** @var array<string, FilesystemOperator> */
    private array $local = [];

    /** @var array<string, FilesystemOperator> */
    private array $remote = [];

    protected function setUp(): void
    {
        foreach ([AdapterFactory::IMAGES, AdapterFactory::THUMBNAILS, AdapterFactory::PRIVATE] as $name) {
            $this->local[$name] = new Filesystem(new InMemoryFilesystemAdapter());
            $this->remote[$name] = new Filesystem(new InMemoryFilesystemAdapter());
        }
    }

    public function testEveryLocalFileArrivesInTheConfiguredStorage(): void
    {
        // Arrange
        $this->local[AdapterFactory::IMAGES]->write('ab/abc.jpg', 'image');
        $this->local[AdapterFactory::THUMBNAILS]->write('abc_100x100.webp', 'thumbnail');
        $this->local[AdapterFactory::PRIVATE]->write('invoices/2026/1.pdf', 'invoice');

        // Act
        $tester = $this->copy([]);

        // Assert
        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertSame('image', $this->remote[AdapterFactory::IMAGES]->read('ab/abc.jpg'));
        static::assertSame('thumbnail', $this->remote[AdapterFactory::THUMBNAILS]->read('abc_100x100.webp'));
        static::assertSame('invoice', $this->remote[AdapterFactory::PRIVATE]->read('invoices/2026/1.pdf'));
    }

    public function testPendingImportsAndHiddenFilesStayBehind(): void
    {
        // Arrange
        $this->local[AdapterFactory::PRIVATE]->write('import/archive/upload.zip', 'zip');
        $this->local[AdapterFactory::IMAGES]->write('.gitkeep', '');

        // Act
        $this->copy([]);

        // Assert
        static::assertFalse($this->remote[AdapterFactory::PRIVATE]->fileExists('import/archive/upload.zip'));
        static::assertFalse($this->remote[AdapterFactory::IMAGES]->fileExists('.gitkeep'));
    }

    public function testASecondRunSkipsWhatArrivedAndReplacesWhatDiffers(): void
    {
        // Arrange
        $this->local[AdapterFactory::IMAGES]->write('same.jpg', 'same');
        $this->local[AdapterFactory::IMAGES]->write('changed.jpg', 'new content');
        $this->remote[AdapterFactory::IMAGES]->write('same.jpg', 'same');
        $this->remote[AdapterFactory::IMAGES]->write('changed.jpg', 'old content');

        // Act
        $tester = $this->copy([]);

        // Assert
        static::assertSame('new content', $this->remote[AdapterFactory::IMAGES]->read('changed.jpg'));
        static::assertMatchesRegularExpression('/images\s+1\s+1\s+0/', $tester->getDisplay());
    }

    public function testReverseCopiesTheConfiguredStorageBackToTheLocalDisk(): void
    {
        // Arrange
        $this->remote[AdapterFactory::THUMBNAILS]->write('abc_100x100.webp', 'thumbnail');

        // Act
        $this->copy(['--reverse' => true]);

        // Assert
        static::assertSame('thumbnail', $this->local[AdapterFactory::THUMBNAILS]->read('abc_100x100.webp'));
    }

    public function testWithoutADsnThereIsNothingToCopy(): void
    {
        // Act
        $tester = $this->copy([], remote: false);

        // Assert
        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('STORAGE_DSN is not set', $tester->getDisplay());
    }

    /** @param array<string, bool> $input */
    private function copy(array $input, bool $remote = true): CommandTester
    {
        $factory = $this->createStub(AdapterFactory::class);
        $factory->method('isRemote')->willReturn($remote);
        $factory->method('createLocal')->willReturnCallback(fn(string $name): FilesystemOperator => $this->local[$name]);
        $factory->method('create')->willReturnCallback(fn(string $name): FilesystemOperator => $this->remote[$name]);

        $tester = new CommandTester(new StorageCopyCommand($factory));
        $tester->execute($input);

        return $tester;
    }
}
