<?php declare(strict_types=1);

namespace Plugin\Fediverse\ActivityPub;

use App\Publisher\PluginSettings\Resolver;
use App\Service\Config\ConfigService as CoreConfigService;
use App\Service\Config\SiteNameResolver;
use App\Service\Media\SiteIconResolver;
use App\Service\Media\SiteLogoResolver;
use App\Service\Seo\UrlOwnerService;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Repository\FollowerRepository;
use Plugin\Fediverse\Service\ConfigService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class Actor
{
    public const string CONTENT_TYPE = 'application/activity+json; charset=utf-8';

    private const string ACTOR_ROUTE = 'app_plugin_fediverse_actor';
    private const string INBOX_ROUTE = 'app_plugin_fediverse_actor_inbox';
    private const string SHARED_INBOX_ROUTE = 'app_plugin_fediverse_inbox';
    private const string OUTBOX_ROUTE = 'app_plugin_fediverse_outbox';
    private const string FOLLOWERS_ROUTE = 'app_plugin_fediverse_followers';
    private const string USERNAME_PATTERN = '/^[a-z0-9_]{1,30}$/';

    public function __construct(
        private Resolver $resolver,
        private CoreConfigService $coreConfig,
        private ConfigService $configService,
        private SiteNameResolver $siteNameResolver,
        private SiteIconResolver $siteIconResolver,
        private SiteLogoResolver $siteLogoResolver,
        private UrlOwnerService $urlOwnerService,
        private FollowerRepository $followerRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function accountOrigin(): string
    {
        return rtrim($this->coreConfig->getHost(), '/');
    }

    public function accountHost(): string
    {
        return (string) parse_url($this->accountOrigin(), PHP_URL_HOST);
    }

    public function id(Account $account): string
    {
        return $this->idFor($account->getUsername());
    }

    public function keyId(Account $account): string
    {
        return $this->id($account) . '#main-key';
    }

    public function usernameOf(string $actorId): ?string
    {
        $position = strrpos($actorId, '/@');
        $username = $position === false ? '' : substr($actorId, $position + 2);

        return preg_match(self::USERNAME_PATTERN, $username) === 1 && $this->idFor($username) === $actorId ? $username : null;
    }

    public function handle(Account $account): string
    {
        return $account->getUsername() . '@' . $this->accountHost();
    }

    public function profileUrl(Account $account): string
    {
        return $this->inScope($account, fn(): string => $this->scopeOrigin() . '/');
    }

    /** @return array<string, mixed> */
    public function document(Account $account): array
    {
        $id = $this->id($account);
        [$name, $profileUrl, $icon] = $this->inScope($account, fn(): array => [
            $this->siteNameResolver->resolve(),
            $this->scopeOrigin() . '/',
            $this->iconUrl(),
        ]);
        $summary = $this->configService->getConfigFor($account->getScopeId())->getAccountSummary();

        $document = [
            '@context' => ['https://www.w3.org/ns/activitystreams', 'https://w3id.org/security/v1'],
            'id' => $id,
            'type' => $account->isInstallation() ? 'Application' : 'Group',
            'preferredUsername' => $account->getUsername(),
            'name' => $name,
            'summary' => $summary === null ? '' : '<p>' . nl2br(htmlspecialchars($summary, ENT_QUOTES | ENT_HTML5)) . '</p>',
            'url' => $profileUrl,
            'inbox' => $this->url(self::INBOX_ROUTE, $account),
            'outbox' => $this->url(self::OUTBOX_ROUTE, $account),
            'followers' => $this->url(self::FOLLOWERS_ROUTE, $account),
            'endpoints' => ['sharedInbox' => $this->accountOrigin() . $this->urlGenerator->generate(self::SHARED_INBOX_ROUTE)],
            'manuallyApprovesFollowers' => false,
            'discoverable' => true,
            'published' => $account->getCreatedAt()->format(DATE_ATOM),
            'publicKey' => [
                'id' => $this->keyId($account),
                'owner' => $id,
                'publicKeyPem' => $account->getPublicKey(),
            ],
        ];
        if ($icon !== null) {
            $document['icon'] = ['type' => 'Image', 'url' => $icon];
        }

        return $document;
    }

    /** @return array<string, mixed> */
    public function followers(Account $account): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $this->url(self::FOLLOWERS_ROUTE, $account),
            'type' => 'OrderedCollection',
            'totalItems' => $this->followerRepository->countFor($account),
        ];
    }

    /** @return array<string, mixed> */
    public function tombstone(Account $account): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $this->id($account),
            'type' => 'Tombstone',
            'formerType' => $account->isInstallation() ? 'Application' : 'Group',
            'deleted' => $account->getRetiredAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @template T
     * @param  callable():T $work
     * @return T
     */
    private function inScope(Account $account, callable $work): mixed
    {
        $scopeId = $account->getScopeId();

        return $scopeId === null ? $work() : $this->resolver->runInScope($scopeId, $work);
    }

    private function idFor(string $username): string
    {
        return $this->accountOrigin() . $this->urlGenerator->generate(self::ACTOR_ROUTE, ['username' => $username]);
    }

    private function url(string $route, Account $account): string
    {
        return $this->accountOrigin() . $this->urlGenerator->generate($route, ['username' => $account->getUsername()]);
    }

    private function scopeOrigin(): string
    {
        return $this->urlOwnerService->getOwnerHost(self::ACTOR_ROUTE);
    }

    private function iconUrl(): ?string
    {
        $url = $this->siteIconResolver->url() ?? $this->siteLogoResolver->resolve()['url'];
        if ($url === '') {
            return null;
        }

        return str_starts_with($url, 'http') ? $url : $this->scopeOrigin() . $url;
    }
}
