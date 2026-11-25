<?php declare(strict_types=1);

namespace Plugin\Fediverse\Publisher\WellKnown;

use App\Publisher\WellKnown\WellKnownDocument;
use App\Publisher\WellKnown\WellKnownProviderInterface;
use Override;
use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\Controller\NodeInfoController;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Repository\AccountRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class NodeInfoProvider implements WellKnownProviderInterface
{
    public const string APPLICATION_REL = 'https://www.w3.org/ns/activitystreams#Application';

    public function __construct(
        private Actor $actor,
        private UrlGeneratorInterface $urlGenerator,
        private AccountRepository $accounts,
    ) {}

    #[Override]
    public function getSuffix(): string
    {
        return 'nodeinfo';
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

        $links = [[
            'rel' => NodeInfoController::SCHEMA,
            'href' => $this->actor->accountOrigin() . $this->urlGenerator->generate('app_plugin_fediverse_nodeinfo'),
        ]];
        $installation = $this->accounts->findLiveForScope(null);
        if ($installation instanceof Account) {
            $links[] = ['rel' => self::APPLICATION_REL, 'href' => $this->actor->id($installation)];
        }

        return WellKnownDocument::json(['links' => $links]);
    }
}
