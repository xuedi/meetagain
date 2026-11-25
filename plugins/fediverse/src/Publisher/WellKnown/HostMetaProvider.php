<?php declare(strict_types=1);

namespace Plugin\Fediverse\Publisher\WellKnown;

use App\Publisher\WellKnown\WellKnownDocument;
use App\Publisher\WellKnown\WellKnownProviderInterface;
use Override;
use Plugin\Fediverse\ActivityPub\Actor;
use Symfony\Component\HttpFoundation\Request;

final readonly class HostMetaProvider implements WellKnownProviderInterface
{
    public function __construct(
        private Actor $actor,
    ) {}

    #[Override]
    public function getSuffix(): string
    {
        return 'host-meta';
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

        $template = htmlspecialchars($this->actor->accountOrigin() . '/.well-known/webfinger?resource={uri}', ENT_XML1 | ENT_QUOTES);

        return WellKnownDocument::of(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . "\n"
            . '<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0"><Link rel="lrdd" type="application/jrd+json" template="'
            . $template
            . '"/></XRD>'
            . "\n",
            'application/xrd+xml; charset=utf-8',
        );
    }
}
