<?php declare(strict_types=1);

namespace App\Anonymization;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Rewrites the personal data in the tables it claims. Every mapped table must be claimed by exactly one
 * implementation, and every text-like column of a claimed table must be rewritten, cleared or kept; the
 * command refuses to run otherwise. Implementations run in ascending order and must be safe to run twice.
 */
#[AutoconfigureTag]
interface AnonymizerInterface
{
    public function getOrder(): int;

    /**
     * @return list<Claim>
     */
    public function getClaims(): array;

    public function anonymize(Context $context): void;
}
