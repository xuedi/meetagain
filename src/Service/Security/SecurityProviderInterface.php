<?php declare(strict_types=1);

namespace App\Service\Security;

use App\Enum\SecurityEventType;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag]
interface SecurityProviderInterface
{
    public function getKey(): string;

    public function getPriority(): int;

    public function handles(SecurityEventType $type): bool;

    /**
     * @param array<string, mixed> $context
     */
    public function observe(SecurityEventType $type, Request $request, array $context, string $sessionId, string $ip, bool $readOnly = false): ProviderReport;

    public function scanRetrospective(DateTimeImmutable $from, DateTimeImmutable $to): ProviderReport;
}
