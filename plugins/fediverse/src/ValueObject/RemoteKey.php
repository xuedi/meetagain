<?php declare(strict_types=1);

namespace Plugin\Fediverse\ValueObject;

final readonly class RemoteKey
{
    public function __construct(
        public string $keyId,
        public string $ownerId,
        public string $publicKeyPem,
        public string $inbox,
        public ?string $sharedInbox,
        public int $fetchedAt,
    ) {}
}
