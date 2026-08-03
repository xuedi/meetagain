<?php declare(strict_types=1);

namespace App\Publisher\WellKnown;

final readonly class WellKnownDocument
{
    private function __construct(
        public string $body,
        public string $contentType,
        public int $maxAge,
        public ?string $redirectTo,
    ) {}

    public static function of(string $body, string $contentType, int $maxAge = 3600): self
    {
        return new self($body, $contentType, $maxAge, null);
    }

    /**
     * @param array<string, mixed>|list<mixed> $data
     */
    public static function json(array $data, string $contentType = 'application/json', int $maxAge = 3600): self
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new self($body . "\n", $contentType, $maxAge, null);
    }

    public static function redirect(string $url): self
    {
        return new self('', 'text/plain; charset=utf-8', 0, $url);
    }
}
