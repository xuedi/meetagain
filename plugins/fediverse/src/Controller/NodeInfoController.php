<?php declare(strict_types=1);

namespace Plugin\Fediverse\Controller;

use App\Kernel;
use App\Service\Config\ConfigService as CoreConfigService;
use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\Service\AccountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fediverse')]
final class NodeInfoController extends AbstractController
{
    public const string SCHEMA = 'http://nodeinfo.diaspora.software/ns/schema/2.1';

    private const int MAX_AGE = 3600;

    /** @param array<string, array{version: string}> $plugins */
    public function __construct(
        private readonly AccountService $accountService,
        private readonly Actor $actor,
        private readonly CoreConfigService $coreConfig,
        #[Autowire(param: Kernel::PLUGINS_PARAMETER)]
        private readonly array $plugins,
    ) {}

    #[Route('/nodeinfo/2.1', name: 'app_plugin_fediverse_nodeinfo', methods: ['GET'])]
    public function show(Request $request): Response
    {
        if ($request->getHost() !== $this->actor->accountHost()) {
            throw $this->createNotFoundException();
        }

        $accounts = $this->accountService->countLive();
        $response = new JsonResponse(null, Response::HTTP_OK, ['Content-Type' => 'application/json; profile="' . self::SCHEMA . '#"']);
        $response->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $response->setData([
            'version' => '2.1',
            'software' => [
                'name' => 'meetagain',
                'version' => $this->plugins['fediverse']['version'] ?? '0',
            ],
            'protocols' => ['activitypub'],
            'services' => ['inbound' => [], 'outbound' => []],
            'openRegistrations' => false,
            'usage' => ['users' => ['total' => $accounts, 'activeMonth' => $accounts, 'activeHalfyear' => $accounts]],
            'metadata' => ['nodeName' => $this->coreConfig->getSiteName()],
        ]);
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);

        return $response;
    }
}
