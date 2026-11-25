<?php declare(strict_types=1);

namespace Plugin\Fediverse\Publisher\WellKnown;

use App\Publisher\WellKnown\WellKnownDocument;
use App\Publisher\WellKnown\WellKnownProviderInterface;
use Override;
use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Repository\AccountRepository;
use Symfony\Component\HttpFoundation\Request;

final readonly class WebFingerProvider implements WellKnownProviderInterface
{
    private const int MAX_AGE = 300;

    public function __construct(
        private AccountRepository $accountRepository,
        private Actor $actor,
    ) {}

    #[Override]
    public function getSuffix(): string
    {
        return 'webfinger';
    }

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    #[Override]
    public function provide(Request $request): ?WellKnownDocument
    {
        if ($request->getHost() !== $this->actor->accountHost()) {
            return null;
        }

        $account = $this->findAccount((string) $request->query->get('resource', ''));
        if (!$account instanceof Account || $account->isRetired()) {
            return null;
        }

        $id = $this->actor->id($account);
        $profileUrl = $this->actor->profileUrl($account);

        return WellKnownDocument::json(
            [
                'subject' => 'acct:' . $this->actor->handle($account),
                'aliases' => [$id, $profileUrl],
                'links' => [
                    ['rel' => 'self', 'type' => 'application/activity+json', 'href' => $id],
                    ['rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => $profileUrl],
                ],
            ],
            'application/jrd+json; charset=utf-8',
            self::MAX_AGE,
        );
    }

    private function findAccount(string $resource): ?Account
    {
        $resource = mb_strtolower(trim($resource));
        $handle = str_starts_with($resource, 'acct:') ? substr($resource, 5) : null;
        if ($handle !== null) {
            [$username, $host] = array_pad(explode('@', ltrim($handle, '@'), 2), 2, '');

            return $host === $this->actor->accountHost() ? $this->accountRepository->findOneByUsername($username) : null;
        }

        $username = $this->actor->usernameOf($resource);

        return $username === null ? null : $this->accountRepository->findOneByUsername($username);
    }
}
