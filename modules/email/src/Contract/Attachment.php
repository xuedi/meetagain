<?php declare(strict_types=1);

namespace Module\Email\Contract;

final readonly class Attachment
{
    /**
     * @param bool $stored the path is a key in the application's private storage rather than a local file
     */
    public function __construct(
        public string $path,
        public string $filename,
        public bool $stored = false,
    ) {}

    /** @param array{path?: mixed, filename?: mixed, stored?: mixed} $row */
    public static function fromArray(array $row): ?self
    {
        $path = $row['path'] ?? null;
        $filename = $row['filename'] ?? null;

        if (!is_string($path) || !is_string($filename) || $path === '' || $filename === '') {
            return null;
        }

        return new self($path, $filename, ($row['stored'] ?? false) === true);
    }

    /** @return array{path: string, filename: string, stored: bool} */
    public function toArray(): array
    {
        return ['path' => $this->path, 'filename' => $this->filename, 'stored' => $this->stored];
    }
}
