<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\TranslationFileManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class TranslationFileManagerTest extends TestCase
{
    private MockObject|Filesystem $fs;
    private string $projectDir;
    private TranslationFileManager $subject;

    protected function setUp(): void
    {
        $this->fs = $this->createStub(Filesystem::class);
        $this->projectDir = sys_get_temp_dir() . '/meetAgain_' . uniqid();
        mkdir($this->projectDir . '/translations', 0777, true);
        $this->subject = new TranslationFileManager($this->fs, $this->projectDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectDir)) {
            $this->removeDir($this->projectDir);
        }
    }

    private function removeDir(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testCleanUpTranslationFilesRemovesPhpFiles(): void
    {
        $this->fs = $this->createMock(Filesystem::class);
        $this->subject = new TranslationFileManager($this->fs, $this->projectDir);
        $this->fs->method('exists')->willReturn(true);
        $this->fs->expects($this->once())->method('exists')->with($this->projectDir . '/translations/');
        
        $this->subject->cleanUpTranslationFiles();
    }

    public function testWriteTranslationFileDumpsFile(): void
    {
        $this->fs = $this->createMock(Filesystem::class);
        $this->subject = new TranslationFileManager($this->fs, $this->projectDir);
        $this->fs->method('exists')->willReturn(true);
        $this->fs->expects($this->once())->method('dumpFile');
        
        $this->subject->writeTranslationFile('de', ['key' => 'value']);
    }

    public function testGetTranslationFilesReturnsEmptyIfPathDoesNotExist(): void
    {
        $this->fs->method('exists')->willReturn(false);
        $result = $this->subject->getTranslationFiles();
        
        $this->assertSame([], $result);
    }
}
