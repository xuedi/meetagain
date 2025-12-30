<?php declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

readonly class TranslationFileManager
{
    public function __construct(
        private Filesystem $fs,
        private string $kernelProjectDir,
    ) {
    }

    public function cleanUpTranslationFiles(): int
    {
        $cleanedUp = 0;
        $path = $this->getTranslationsPath();
        if (!$this->fs->exists($path)) {
            return 0;
        }
        $finder = new Finder();
        $finder->files()->in($path)->depth(0)->name(['*.php']);
        foreach ($finder as $file) {
            $this->fs->remove($file->getPathname());
            ++$cleanedUp;
        }

        return $cleanedUp;
    }

    public function writeTranslationFile(string $locale, array $translations): void
    {
        $path = $this->getTranslationsPath();
        if (!$this->fs->exists($path)) {
            $this->fs->mkdir($path);
        }
        $file = $path . 'messages.' . $locale . '.php';
        $content = '<?php return ' . var_export($translations, true) . ';' . PHP_EOL;
        $this->fs->dumpFile($file, $content);
    }

    public function getTranslationFiles(): iterable
    {
        $finder = new Finder();
        $path = $this->getTranslationsPath();
        if (!$this->fs->exists($path)) {
            return [];
        }

        return $finder->files()->in($path)->depth(0)->name(['messages*.php']);
    }

    private function getTranslationsPath(): string
    {
        return $this->kernelProjectDir . '/translations/';
    }
}
