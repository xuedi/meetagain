<?php declare(strict_types=1);

namespace Plugin\Fediverse\Controller;

use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\ActivityPub\EventObject;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Entity\Publication;
use Plugin\Fediverse\Service\AccountService;
use Plugin\Fediverse\Service\Publisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fediverse')]
final class ActorController extends AbstractController
{
    private const int MAX_AGE = 300;

    public function __construct(
        private readonly AccountService $accountService,
        private readonly Actor $actor,
        private readonly EventObject $eventObject,
        private readonly Publisher $publisher,
    ) {}

    #[Route('/@{username}', name: 'app_plugin_fediverse_actor', requirements: ['username' => '[a-z0-9_]{1,30}'], methods: ['GET'])]
    public function show(Request $request, string $username): Response
    {
        $account = $this->accountService->findByUsername($username);
        if (!$account instanceof Account) {
            throw $this->createNotFoundException();
        }

        if ($request->getSchemeAndHttpHost() !== $this->actor->accountOrigin()) {
            return new RedirectResponse($this->actor->id($account), Response::HTTP_MOVED_PERMANENTLY);
        }

        if ($account->isRetired()) {
            return $this->activityJson($this->actor->tombstone($account), Response::HTTP_GONE);
        }

        if ($this->wantsHtml($request)) {
            return new RedirectResponse($this->actor->profileUrl($account));
        }

        return $this->activityJson($this->actor->document($account), Response::HTTP_OK);
    }

    #[Route('/@{username}/followers', name: 'app_plugin_fediverse_followers', requirements: ['username' => '[a-z0-9_]{1,30}'], methods: ['GET'])]
    public function followers(Request $request, string $username): Response
    {
        $account = $this->accountService->findByUsername($username);
        if (!$account instanceof Account || $account->isRetired() || $request->getSchemeAndHttpHost() !== $this->actor->accountOrigin()) {
            throw $this->createNotFoundException();
        }

        return $this->activityJson($this->actor->followers($account), Response::HTTP_OK);
    }

    #[Route('/@{username}/outbox', name: 'app_plugin_fediverse_outbox', requirements: ['username' => '[a-z0-9_]{1,30}'], methods: ['GET'])]
    public function outbox(Request $request, string $username): Response
    {
        $account = $this->accountService->findByUsername($username);
        if (!$account instanceof Account || $account->isRetired() || $request->getSchemeAndHttpHost() !== $this->actor->accountOrigin()) {
            throw $this->createNotFoundException();
        }

        return $this->activityJson($this->eventObject->outbox($account), Response::HTTP_OK);
    }

    #[Route('/events/{id}', name: 'app_plugin_fediverse_event', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function event(Request $request, int $id): Response
    {
        $publication = $this->publisher->find($id);
        if (!$publication instanceof Publication) {
            throw $this->createNotFoundException();
        }

        if ($request->getSchemeAndHttpHost() !== $this->actor->accountOrigin()) {
            return new RedirectResponse($this->eventObject->id($publication), Response::HTTP_MOVED_PERMANENTLY);
        }

        if ($publication->isDeleted()) {
            return $this->activityJson($this->eventObject->tombstone($publication), Response::HTTP_GONE);
        }

        $document = $publication->getDocument();
        if ($this->wantsHtml($request) && is_string($document['url'] ?? null)) {
            return new RedirectResponse($document['url']);
        }

        return $this->activityJson($document, Response::HTTP_OK);
    }

    /** @param array<string, mixed> $document */
    private function activityJson(array $document, int $status): JsonResponse
    {
        $response = new JsonResponse(null, $status, ['Content-Type' => Actor::CONTENT_TYPE, 'Vary' => 'Accept']);
        $response->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $response->setData($document);
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);

        return $response;
    }

    private function wantsHtml(Request $request): bool
    {
        $preferred = $request->getAcceptableContentTypes()[0] ?? '';

        return $preferred === 'text/html' || $preferred === 'application/xhtml+xml';
    }
}
