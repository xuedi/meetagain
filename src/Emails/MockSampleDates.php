<?php declare(strict_types=1);

namespace App\Emails;

final readonly class MockSampleDates
{
    public function __construct(
        public string $createdAt,
        public string $expiresAt,
        public string $chargeDate,
        public string $retryDate,
        public string $suspendedSince,
    ) {}
}
