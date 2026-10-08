<?php declare(strict_types=1);

namespace Module\Suggestion\Tests\Stub;

use InvalidArgumentException;
use Module\Suggestion\Contract\TargetProviderInterface;
use Override;

class Target implements TargetProviderInterface
{
    public const string TYPE = 'stub_suggestion';

    public ?string $verdict = null;

    /** @var list<int> */
    public array $reviewerIds = [];

    /** @var array<int, string> */
    public array $reviewerScopes = [];

    /** @var list<array{name: string, proposerId: int, scope: ?string}> */
    public array $created = [];

    public function __construct(
        private readonly Scope $scope,
    ) {}

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
    public function getLabelKey(): string
    {
        return 'stub.label';
    }

    #[Override]
    public function getFormType(): string
    {
        return DraftType::class;
    }

    #[Override]
    public function newDraft(): object
    {
        return new Draft();
    }

    #[Override]
    public function fromPayload(array $payload): object
    {
        $draft = new Draft();
        $draft->name = (string) ($payload['name'] ?? '');

        return $draft;
    }

    #[Override]
    public function toPayload(object $draft): array
    {
        return ['name' => $this->draft($draft)->name];
    }

    #[Override]
    public function describe(array $payload): string
    {
        return 'Stub ' . (string) ($payload['name'] ?? '');
    }

    #[Override]
    public function summaryRows(array $payload): array
    {
        return [['label' => 'Name', 'value' => (string) ($payload['name'] ?? '')]];
    }

    #[Override]
    public function canPropose(int $userId): bool
    {
        return true;
    }

    #[Override]
    public function canReview(int $userId): bool
    {
        if (!in_array($userId, $this->reviewerIds, true)) {
            return false;
        }

        return !isset($this->reviewerScopes[$userId]) || $this->reviewerScopes[$userId] === $this->scope->current;
    }

    #[Override]
    public function validate(object $draft): ?string
    {
        return $this->verdict;
    }

    #[Override]
    public function create(object $draft, int $proposerId): int
    {
        $this->created[] = ['name' => $this->draft($draft)->name, 'proposerId' => $proposerId, 'scope' => $this->scope->current];

        return 1000 + count($this->created);
    }

    private function draft(object $draft): Draft
    {
        if (!$draft instanceof Draft) {
            throw new InvalidArgumentException(sprintf('Expected a stub draft, got %s', $draft::class));
        }

        return $draft;
    }
}
