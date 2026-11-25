<?php declare(strict_types=1);

namespace Plugin\Fediverse\Controller;

use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\Service\InboxService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fediverse')]
final class InboxController extends AbstractController
{
    public function __construct(
        private readonly InboxService $inboxService,
        private readonly Actor $actor,
    ) {}

    #[Route('/inbox', name: 'app_plugin_fediverse_inbox', methods: ['POST'])]
    #[Route('/@{username}/inbox', name: 'app_plugin_fediverse_actor_inbox', requirements: ['username' => '[a-z0-9_]{1,30}'], methods: ['POST'])]
    public function receive(Request $request): Response
    {
        if ($request->getSchemeAndHttpHost() !== $this->actor->accountOrigin()) {
            throw $this->createNotFoundException();
        }

        return new Response(null, $this->inboxService->receive($request));
    }
}
