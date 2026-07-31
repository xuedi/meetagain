<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Publisher\PluginSettings\Resolver;

final readonly class ScopeCodec
{
    public const int GLOBAL_TARGET = 0;

    public function __construct(
        private Resolver $resolver,
    ) {}

    public function currentTargetId(): ?int
    {
        return $this->encode($this->resolver->resolveScopeId());
    }

    public function encode(?string $scopeId): ?int
    {
        if ($scopeId === null) {
            return self::GLOBAL_TARGET;
        }

        return ctype_digit($scopeId) && (int) $scopeId > 0 ? (int) $scopeId : null;
    }

    public function decode(int $targetId): ?string
    {
        return $targetId === self::GLOBAL_TARGET ? null : (string) $targetId;
    }
}
