<?php declare(strict_types=1);

namespace Plugin\Fediverse\Security;

use App\Service\Security\SecretBoxConsumerInterface;
use Override;
use Plugin\Fediverse\Repository\AccountRepository;

final readonly class PrivateKeyConsumer implements SecretBoxConsumerInterface
{
    public function __construct(
        private AccountRepository $accountRepository,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'fediverse_config.secretbox_label';
    }

    #[Override]
    public function count(): int
    {
        return $this->accountRepository->count([]);
    }
}
