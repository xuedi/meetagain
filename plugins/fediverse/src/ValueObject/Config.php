<?php declare(strict_types=1);

namespace Plugin\Fediverse\ValueObject;

use App\Publisher\PluginSettings\Data;
use Plugin\Fediverse\Enum\Content;
use Plugin\Fediverse\Enum\Location;

final class Config implements Data
{
    public const string USERNAME_PATTERN = '/^[a-z0-9_]+$/';
    public const int USERNAME_MAX = 30;
    public const int ACCOUNT_SUMMARY_MAX = 500;

    private Content $content = Content::Summary;
    private Location $location = Location::City;
    private bool $image = true;
    private ?string $username = null;
    private ?string $accountSummary = null;
    private bool $announce = true;
    private bool $active = false;

    /** @var list<string> */
    private array $blockedServers = [];

    public static function isUsername(string $username): bool
    {
        return strlen($username) <= self::USERNAME_MAX && preg_match(self::USERNAME_PATTERN, $username) === 1;
    }

    public function getContent(): Content
    {
        return $this->content;
    }

    public function setContent(Content $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function setLocation(Location $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function isImage(): bool
    {
        return $this->image;
    }

    public function setImage(bool $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = self::trimToNull($username);

        return $this;
    }

    public function getAccountSummary(): ?string
    {
        return $this->accountSummary;
    }

    public function setAccountSummary(?string $accountSummary): static
    {
        $this->accountSummary = self::trimToNull($accountSummary);

        return $this;
    }

    public function isAnnounce(): bool
    {
        return $this->announce;
    }

    public function setAnnounce(bool $announce): static
    {
        $this->announce = $announce;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /** @return list<string> */
    public function getBlockedServers(): array
    {
        return $this->blockedServers;
    }

    /** @param iterable<string> $blockedServers */
    public function setBlockedServers(iterable $blockedServers): static
    {
        $normalised = [];
        foreach ($blockedServers as $server) {
            $server = mb_strtolower(trim($server));
            if ($server !== '') {
                $normalised[$server] = $server;
            }
        }
        $this->blockedServers = array_values($normalised);

        return $this;
    }

    public function blocks(string $host): bool
    {
        $host = mb_strtolower($host);
        foreach ($this->blockedServers as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.' . $blocked)) {
                return true;
            }
        }

        return false;
    }

    public function clampedTo(self $ceiling): self
    {
        $clamped = clone $this;
        $clamped->content = $this->content->atMost($ceiling->content);
        $clamped->location = $this->location->atMost($ceiling->location);
        $clamped->image = $this->image && $ceiling->image;
        $clamped->announce = $ceiling->announce;
        $clamped->blockedServers = $ceiling->blockedServers;

        return $clamped;
    }

    public function withoutAccount(): self
    {
        $copy = clone $this;
        $copy->username = null;
        $copy->accountSummary = null;
        $copy->active = false;

        return $copy;
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content->value,
            'location' => $this->location->value,
            'image' => $this->image,
            'username' => $this->username,
            'accountSummary' => $this->accountSummary,
            'announce' => $this->announce,
            'active' => $this->active,
            'blockedServers' => $this->blockedServers,
        ];
    }

    public static function fromArray(array $raw): static
    {
        $config = new self();
        $config->content = Content::tryFrom((string) ($raw['content'] ?? '')) ?? $config->content;
        $config->location = Location::tryFrom((string) ($raw['location'] ?? '')) ?? $config->location;
        $config->image = (bool) ($raw['image'] ?? $config->image);
        $config->setUsername(is_string($raw['username'] ?? null) ? $raw['username'] : null);
        $config->setAccountSummary(is_string($raw['accountSummary'] ?? null) ? $raw['accountSummary'] : null);
        $config->announce = (bool) ($raw['announce'] ?? $config->announce);
        $config->active = (bool) ($raw['active'] ?? $config->active);
        if (is_array($raw['blockedServers'] ?? null)) {
            $config->setBlockedServers(array_map(strval(...), array_filter($raw['blockedServers'], is_scalar(...))));
        }

        return $config;
    }

    private static function trimToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
