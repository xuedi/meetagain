<?php declare(strict_types=1);

namespace App\Entity\BlockType;

final readonly class Link
{
    private const array SCHEMES = ['http', 'https', 'mailto', 'tel'];

    private function __construct(
        public string $href,
    ) {}

    public static function fromString(string $raw): self
    {
        $href = trim($raw);
        $bare = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $href);
        $hasScheme = preg_match('/^([a-z][a-z0-9+.\-]*):/i', $bare, $match) === 1;

        if ($hasScheme && !in_array(strtolower($match[1]), self::SCHEMES, true)) {
            return new self('');
        }

        return new self($href);
    }
}
