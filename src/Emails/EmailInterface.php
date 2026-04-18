<?php declare(strict_types=1);

namespace App\Emails;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface EmailInterface
{
    public function getIdentifier(): string;

    /** @return array{subject: string, context: array<string, mixed>} */
    public function getDisplayMockData(): array;

    public function guardCheck(array $context): bool;

    public function send(array $context): void;
}
