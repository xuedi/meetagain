<?php declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\DeveloperAppApplication;
use App\Entity\User;
use App\Enum\DeveloperAppStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\Model\RefreshToken;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use RuntimeException;
use Throwable;

final readonly class DeveloperAppApprovalService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClientManagerInterface $clientManager,
    ) {}

    public function approve(DeveloperAppApplication $app, User $reviewer): string
    {
        if ($app->getClientIdentifier() !== null) {
            throw new RuntimeException('Application already linked to an OAuth client.');
        }

        $secret = bin2hex(random_bytes(32));
        $identifier = $this->buildClientIdentifier($app);

        $this->entityManager->beginTransaction();
        try {
            $client = new Client($app->getAppName(), $identifier, $secret);
            $client->setActive(true);
            $client->setGrants(...array_map(
                static fn(string $grant): Grant => new Grant($grant),
                $app->getRequestedGrants(),
            ));
            $scopes = $app->getRequestedScopes();
            if ($scopes === []) {
                $scopes = ['api'];
            }
            $client->setScopes(...array_map(
                static fn(string $scope): Scope => new Scope($scope),
                $scopes,
            ));
            $client->setRedirectUris(...array_map(
                static fn(string $uri): RedirectUri => new RedirectUri($uri),
                $app->getRedirectUris(),
            ));

            $this->clientManager->save($client);

            $app->setStatus(DeveloperAppStatus::Approved);
            $app->setReviewedBy($reviewer);
            $app->setReviewedAt(new DateTimeImmutable());
            $app->setClientIdentifier($identifier);
            $app->setDenyReason(null);
            $app->setUserReadOutcome(false);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $e) {
            $this->entityManager->rollback();

            throw $e;
        }

        return $secret;
    }

    public function deny(DeveloperAppApplication $app, User $reviewer, string $reason): void
    {
        $app->setStatus(DeveloperAppStatus::Denied);
        $app->setReviewedBy($reviewer);
        $app->setReviewedAt(new DateTimeImmutable());
        $app->setDenyReason($reason);
        $app->setUserReadOutcome(false);

        $this->entityManager->flush();
    }

    public function revoke(DeveloperAppApplication $app, User $reviewer, string $reason): void
    {
        if ($app->getStatus() !== DeveloperAppStatus::Approved) {
            throw new RuntimeException('Only approved applications can be revoked.');
        }

        $clientIdentifier = $app->getClientIdentifier();
        if ($clientIdentifier === null) {
            throw new RuntimeException('Approved application has no linked OAuth client.');
        }

        $this->entityManager->beginTransaction();
        try {
            $this->revokeTokensForClient($clientIdentifier);

            $client = $this->clientManager->find($clientIdentifier);
            if ($client !== null) {
                $client->setActive(false);
                $this->clientManager->save($client);
            }

            $app->setStatus(DeveloperAppStatus::Revoked);
            $app->setReviewedBy($reviewer);
            $app->setReviewedAt(new DateTimeImmutable());
            $app->setDenyReason($reason);
            $app->setUserReadOutcome(false);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $e) {
            $this->entityManager->rollback();

            throw $e;
        }
    }

    public function deleteByOwner(DeveloperAppApplication $app): void
    {
        $clientIdentifier = $app->getClientIdentifier();

        $this->entityManager->beginTransaction();
        try {
            if ($clientIdentifier !== null) {
                $this->revokeTokensForClient($clientIdentifier);
                $client = $this->clientManager->find($clientIdentifier);
                if ($client !== null) {
                    $this->clientManager->remove($client);
                }
            }

            $this->entityManager->remove($app);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $e) {
            $this->entityManager->rollback();

            throw $e;
        }
    }

    private function revokeTokensForClient(string $clientIdentifier): void
    {
        $accessTokenIdsBuilder = $this->entityManager->createQueryBuilder()
            ->select('at.identifier')
            ->from(AccessToken::class, 'at')
            ->where('at.client = :client');

        $refreshQb = $this->entityManager->createQueryBuilder();
        $refreshQb
            ->update(RefreshToken::class, 'rt')
            ->set('rt.revoked', ':revoked')
            ->where($refreshQb->expr()->in('rt.accessToken', $accessTokenIdsBuilder->getDQL()))
            ->setParameter('revoked', true)
            ->setParameter('client', $clientIdentifier, 'string')
            ->getQuery()
            ->execute();

        $this->entityManager->createQueryBuilder()
            ->update(AccessToken::class, 'at')
            ->set('at.revoked', ':revoked')
            ->where('at.client = :client')
            ->setParameter('revoked', true)
            ->setParameter('client', $clientIdentifier, 'string')
            ->getQuery()
            ->execute();
    }

    private function buildClientIdentifier(DeveloperAppApplication $app): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $app->getAppName()) ?? '');
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'app';
        }
        $slug = substr($slug, 0, 40);

        $shortUuid = substr(bin2hex(random_bytes(8)), 0, 12);

        return $slug . '-' . $shortUuid;
    }
}
