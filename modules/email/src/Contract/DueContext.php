<?php declare(strict_types=1);

namespace Module\Email\Contract;

final readonly class DueContext
{
    /**
     * @param array<string, mixed> $data context passed to send() (merged with ['user' => $user])
     * @param array<mixed> $potentialRecipients users to iterate; each is merged in as 'user'
     */
    public function __construct(
        public array $data,
        public array $potentialRecipients,
    ) {}
}
