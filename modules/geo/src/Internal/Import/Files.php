<?php declare(strict_types=1);

namespace Module\Geo\Internal\Import;

use Generator;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

readonly class Files
{
    private const int NOT_FOUND = 404;
    private const int OK = 200;
    private const int TIMEOUT_SECONDS = 60;

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    /**
     * @return Generator<int, list<string>>
     */
    public function rows(Options $options, string $path, bool $optional = false): Generator
    {
        $temporary = $options->sourceDir === null;
        $file = $temporary ? $this->download($options->baseUrl, $path) : $this->local($options->sourceDir, $path);
        if ($file === null) {
            if ($optional) {
                return;
            }
            throw new RuntimeException(sprintf('GeoNames file %s not found.', $path));
        }

        $stream = str_ends_with($file, '.zip') ? fopen('zip://' . $file . '#' . basename($path, '.zip') . '.txt', 'r') : fopen($file, 'r');
        try {
            if ($stream === false) {
                throw new RuntimeException(sprintf('GeoNames file %s cannot be read.', $path));
            }
            while (($line = fgets($stream)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                yield explode("\t", $line);
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if ($temporary) {
                unlink($file);
            }
        }
    }

    private function local(string $directory, string $path): ?string
    {
        $candidates = [rtrim($directory, '/') . '/' . $path];
        if (str_ends_with($path, '.zip')) {
            $candidates[] = rtrim($directory, '/') . '/' . substr($path, 0, -4) . '.txt';
        }

        return array_find($candidates, static fn(string $candidate): bool => is_file($candidate));
    }

    private function download(string $baseUrl, string $path): ?string
    {
        $response = $this->httpClient->request('GET', rtrim($baseUrl, '/') . '/' . $path, ['timeout' => self::TIMEOUT_SECONDS]);
        $status = $response->getStatusCode();
        if ($status === self::NOT_FOUND) {
            return null;
        }
        if ($status !== self::OK) {
            throw new RuntimeException(sprintf('GeoNames answered %d for %s.', $status, $path));
        }

        $file = tempnam(sys_get_temp_dir(), 'geonames-');
        if ($file === false) {
            throw new RuntimeException('No temporary file for the GeoNames download.');
        }
        $target = $file . (str_ends_with($path, '.zip') ? '.zip' : '.txt');
        try {
            rename($file, $target);
            $this->save($response, $target);
        } catch (Throwable $exception) {
            array_map(unlink(...), array_filter([$file, $target], is_file(...)));
            throw $exception;
        }

        return $target;
    }

    private function save(ResponseInterface $response, string $target): void
    {
        $handle = fopen($target, 'w');
        if ($handle === false) {
            throw new RuntimeException('The GeoNames download cannot be written.');
        }
        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($handle, $chunk->getContent());
            }
        } finally {
            fclose($handle);
        }
    }
}
