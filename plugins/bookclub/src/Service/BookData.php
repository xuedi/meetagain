<?php declare(strict_types=1);

namespace Plugin\Bookclub\Service;

readonly class BookData
{
    public function __construct(
        public string $isbn,
        public string $title,
        public ?string $author = null,
        public ?string $description = null,
        public ?int $pageCount = null,
        public ?int $publishedYear = null,
        public ?string $coverUrl = null,
    ) {}
}
