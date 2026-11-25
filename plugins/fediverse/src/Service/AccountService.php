<?php declare(strict_types=1);

namespace Plugin\Fediverse\Service;

use App\Publisher\PluginSettings\Resolver;
use App\Service\Config\PluginService;
use App\Service\Security\SecretBox;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Repository\AccountRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Lock\LockFactory;

readonly class AccountService
{
    private const int KEY_BITS = 2048;
    private const string PLUGIN_KEY = 'fediverse';

    public function __construct(
        private AccountRepository $repository,
        private ConfigService $configService,
        private SecretBox $secretBox,
        private Resolver $resolver,
        private PluginService $pluginService,
        private EntityManagerInterface $em,
        private LockFactory $lockFactory,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function findByUsername(string $username): ?Account
    {
        return $this->repository->findOneByUsername($username);
    }

    public function countLive(): int
    {
        return $this->repository->countLive();
    }

    public function isFederating(Account $account): bool
    {
        $scopeId = $account->getScopeId();
        if ($scopeId === null) {
            return true;
        }

        return $this->resolver->runInScope(
            $scopeId,
            fn(): bool => $this->resolver->resolveScopeId() === $scopeId && in_array(self::PLUGIN_KEY, $this->pluginService->getActiveList(), true),
        );
    }

    public function retire(Account $account): void
    {
        $lock = $this->lockFactory->createLock('fediverse.account.' . ($account->getScopeId() ?? ''));
        $lock->acquire(true);

        try {
            $account->retire($this->clock->now());
            $this->em->flush();
        } finally {
            $lock->release();
        }
    }

    public function ensureForScope(?string $scopeId): ?Account
    {
        $username = $this->configService->getConfigFor($scopeId)->getUsername();
        $lock = $this->lockFactory->createLock('fediverse.account.' . ($scopeId ?? ''));
        $lock->acquire(true);

        try {
            $account = $this->repository->findLiveForScope($scopeId);
            if ($account instanceof Account) {
                return $username === null ? $account : $this->rename($account, $username);
            }

            return $username === null ? null : $this->create($scopeId, $username);
        } finally {
            $lock->release();
        }
    }

    private function rename(Account $account, string $username): Account
    {
        $isUnchanged = $account->getUsername() === $username;
        if ($isUnchanged || $account->isLocked()) {
            return $account;
        }

        if ($this->repository->isUsernameTakenOutsideScope($username, $account->getScopeId())) {
            $this->logger->warning('Fediverse: a username chosen for a scope is taken; the account keeps its old one.', [
                'scopeId' => $account->getScopeId(),
                'username' => $username,
            ]);

            return $account;
        }

        $account->setUsername($username);
        $this->em->flush();

        return $account;
    }

    private function create(?string $scopeId, string $username): ?Account
    {
        if ($this->repository->isUsernameTakenOutsideScope($username, $scopeId)) {
            $this->logger->warning('Fediverse: a username chosen for a scope is taken; no account was created.', [
                'scopeId' => $scopeId,
                'username' => $username,
            ]);

            return null;
        }

        [$publicKey, $privateKey] = $this->generateKeyPair();
        $account = new Account($scopeId, $username, $publicKey, $this->secretBox->encrypt($privateKey), $this->clock->now());

        $this->em->persist($account);
        $this->em->flush();

        return $account;
    }

    /** @return array{string, string} the public and the private key, PEM-encoded */
    private function generateKeyPair(): array
    {
        $privateKey = null;
        $key = openssl_pkey_new(['private_key_bits' => self::KEY_BITS, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $publicKey = $key === false ? null : openssl_pkey_get_details($key)['key'] ?? null;
        if ($key === false || !is_string($publicKey) || !openssl_pkey_export($key, $privateKey) || !is_string($privateKey)) {
            throw new RuntimeException('Fediverse: generating an account key pair failed.');
        }

        return [$publicKey, $privateKey];
    }
}
