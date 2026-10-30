<?php declare(strict_types=1);

namespace Module\Proposal\Tests\Stub\Change;

use Module\Proposal\Contract\Change\ScopedTargetInterface;
use Module\Proposal\Contract\Change\TargetProviderInterface;
use Override;

class Target implements TargetProviderInterface, ScopedTargetInterface
{
    public const string TYPE = 'stub_change';
    public const string SCOPE = 'stub_wide';
    public const int MISSING = 404;

    public ?string $verdict = null;

    /** @var list<int> */
    public array $reviewerIds = [];

    /** @var list<array{targetId: int, field: string, value: ?string}> */
    public array $applied = [];

    /** @var list<array{targetId: int, scope: string, field: string, value: ?string}> */
    public array $spread = [];

    #[Override]
    public function getPluginKey(): string
    {
        return '';
    }

    #[Override]
    public function getTargetType(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function getTargetLabel(int $targetId): ?string
    {
        return $targetId === self::MISSING ? null : 'Stub ' . $targetId;
    }

    #[Override]
    public function getTargetUrl(int $targetId): ?string
    {
        return null;
    }

    #[Override]
    public function getFieldLabel(string $field): string
    {
        return ucfirst($field);
    }

    #[Override]
    public function formatValue(string $field, ?string $value): string
    {
        return (string) $value;
    }

    #[Override]
    public function canPropose(int $userId, int $targetId): bool
    {
        return true;
    }

    #[Override]
    public function canReview(int $userId, int $targetId): bool
    {
        return in_array($userId, $this->reviewerIds, true);
    }

    #[Override]
    public function validate(int $targetId, string $field, ?string $value): ?string
    {
        return $this->verdict;
    }

    #[Override]
    public function apply(int $targetId, string $field, ?string $value): void
    {
        $this->applied[] = ['targetId' => $targetId, 'field' => $field, 'value' => $value];
    }

    #[Override]
    public function getApplyScope(int $targetId): ?array
    {
        return ['key' => self::SCOPE, 'label' => 'Every stub row'];
    }

    #[Override]
    public function applyToScope(int $targetId, string $scope, string $field, ?string $value): void
    {
        $this->spread[] = ['targetId' => $targetId, 'scope' => $scope, 'field' => $field, 'value' => $value];
    }
}
