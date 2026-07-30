<?php declare(strict_types=1);

namespace App\Emails;

final readonly class Attachment
{
    public function __construct(
        public string $path,
        public string $filename,
    ) {}

    /** @param array{path?: mixed, filename?: mixed} $row */
    public static function fromArray(array $row): ?self
    {
        $path = $row['path'] ?? null;
        $filename = $row['filename'] ?? null;

        if (!is_string($path) || !is_string($filename) || $path === '' || $filename === '') {
            return null;
        }

        return new self($path, $filename);
    }

    /** @return array{path: string, filename: string} */
    public function toArray(): array
    {
        return ['path' => $this->path, 'filename' => $this->filename];
    }
}
