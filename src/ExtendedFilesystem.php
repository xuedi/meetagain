<?php declare(strict_types=1);

namespace App;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * Extended Filesystem service that adds functionality not available in Symfony's Filesystem.
 * This class extends Symfony's Filesystem and adds methods from the original FilesystemService.
 */
class ExtendedFilesystem extends SymfonyFilesystem
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fileExists(string $filename): bool
    {
        return file_exists($filename);
    }

    public function getFileContents(string $filename): string|false
    {
        try {
            return $this->readFile($filename);
        } catch (Exception $e) {
            $this->logger->error(sprintf("Error reading file '%s': %s", $filename, $e->getMessage()));

            return false;
        }
    }

    public function putFileContents(string $filename, string $data): bool
    {
        try {
            $this->dumpFile($filename, $data);

            return true;
        } catch (Exception $e) {
            $this->logger->error(sprintf("Error writing to file '%s': %s", $filename, $e->getMessage()));

            return false;
        }
    }

    public function getRealPath(string $path): string|false
    {
        return realpath($path);
    }

    public function glob(string $pattern, int $flags = 0): array
    {
        $result = glob($pattern, $flags);

        return $result !== false ? $result : [];
    }

    public function scanDirectory(string $directory): array
    {
        try {
            $result = scandir($directory);

            return $result !== false ? $result : [];
        } catch (Exception $e) {
            $this->logger->error(sprintf("Error scanning directory '%s': %s", $directory, $e->getMessage()));

            return [];
        }
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    public function getDirname(string $path, int $levels = 1): string
    {
        return dirname($path, $levels);
    }
}
