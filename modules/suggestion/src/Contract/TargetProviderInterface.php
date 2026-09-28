<?php declare(strict_types=1);

namespace Module\Suggestion\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Registers one entity type as able to be suggested into existence. The registry keys providers by
 * getTargetType() and shows only those whose owning plugin is active. The provider hands back an
 * unsaved draft entity and the form type that edits it, so the proposer's form and the reviewer's
 * form are the same form and the stored payload is that form's round-tripped data.
 */
#[AutoconfigureTag]
interface TargetProviderInterface
{
    /** Directory key of the owning plugin; the empty string marks a core provider. */
    public function getPluginKey(): string;

    /** Registry key for this target type; the value stored with every suggestion of it. */
    public function getTargetType(): string;

    /** Translation key for the type's label. */
    public function getLabelKey(): string;

    /** FQCN of the form type used both to propose and to review. */
    public function getFormType(): string;

    public function newDraft(): object;

    /** @param array<string, scalar|null> $payload */
    public function fromPayload(array $payload): object;

    /** @return array<string, scalar|null> */
    public function toPayload(object $draft): array;

    /**
     * Pre-translated one-line description for the review hub.
     *
     * @param array<string, scalar|null> $payload
     */
    public function describe(array $payload): string;

    /**
     * @param array<string, scalar|null> $payload
     *
     * @return list<array{label: string, value: string}>
     */
    public function summaryRows(array $payload): array;

    public function canPropose(int $userId): bool;

    public function canReview(int $userId): bool;

    /** Pre-translated error when the draft cannot become a row; null when valid. */
    public function validate(object $draft): ?string;

    /** Persists the draft and returns the new row id. Only called after validate() returned null. */
    public function create(object $draft, int $proposerId): int;
}
