<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use DateTimeImmutable;
use Plugin\Glossary\Enum\Grade;
use Plugin\Glossary\Enum\Mode;
use Plugin\Glossary\Enum\Scope;
use Plugin\Glossary\Form\TrainerSetupType;
use Plugin\Glossary\Service\GlossaryService;
use Plugin\Glossary\Service\ProgressService;
use Plugin\Glossary\Service\TrainerService;
use Plugin\Glossary\ValueObject\TrainerSession;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/glossary/trainer')]
#[IsGranted('ROLE_USER')]
final class TrainerController extends AbstractGlossaryController
{
    private const string SESSION_KEY = 'glossary_trainer';

    public function __construct(
        GlossaryService $service,
        private readonly TrainerService $trainer,
        private readonly ProgressService $progress,
    ) {
        parent::__construct($service);
    }

    #[Route('', name: 'app_plugin_glossary_trainer', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->guard();

        $scope = Scope::tryFrom((string) $request->query->get('scope')) ?? Scope::Selection;
        $tagIds = $this->tagIds($request->query->all('tag'));

        return $this->renderIndex($request, $this->setupForm($scope, $tagIds), $scope, $tagIds);
    }

    #[Route('/start', name: 'app_plugin_glossary_trainer_start', methods: ['POST'])]
    public function start(Request $request): Response
    {
        $this->guard();

        $form = $this->setupForm(Scope::Selection, []);
        $form->handleRequest($request);
        $data = $form->getData();
        $scope = $data['scope'] ?? Scope::Selection;
        $tagIds = $this->tagIds(explode(',', (string) ($data['tags'] ?? '')));

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderIndex($request, $form, $scope, $tagIds, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $session = $this->trainer->start(
            $this->userId(),
            $scope,
            $tagIds,
            $data['mode'],
            $data['direction'],
            $data['answerMode'],
            (int) $data['size'],
            new DateTimeImmutable(),
        );
        if ($session->isFinished()) {
            $this->addFlash('warning', 'glossary_trainer.flash_nothing_to_train');

            return $this->redirectToRoute('app_plugin_glossary_trainer', ['scope' => $scope->value, 'tag' => $tagIds]);
        }

        $request->getSession()->set(self::SESSION_KEY, $session->toArray());

        return $this->redirectToRoute('app_plugin_glossary_trainer_card');
    }

    #[Route('/card', name: 'app_plugin_glossary_trainer_card', methods: ['GET'])]
    public function card(Request $request): Response
    {
        $this->guard();

        $session = $this->session($request);
        if ($session === null) {
            return $this->redirectToRoute('app_plugin_glossary_trainer');
        }

        $question = $this->trainer->question($session, $this->userId());
        if ($question === null) {
            return $this->redirectToRoute('app_plugin_glossary_trainer_results');
        }

        return $this->renderPage('@Glossary/trainer/card.html.twig', [
            'session' => $session,
            'question' => $question,
            'revealed' => $request->query->getBoolean('reveal'),
            'grades' => Grade::cases(),
        ]);
    }

    #[Route('/answer', name: 'app_plugin_glossary_trainer_answer', methods: ['POST'])]
    public function answer(Request $request): Response
    {
        $this->guard();
        if (!$this->isCsrfTokenValid('glossary_trainer_answer', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $session = $this->session($request);
        if ($session === null) {
            return $this->redirectToRoute('app_plugin_glossary_trainer');
        }

        $session = $this->trainer->answer(
            $session,
            $this->userId(),
            $request->request->getInt('entry'),
            Grade::tryFrom($request->request->getInt('grade')),
            $request->request->has('typed') ? (string) $request->request->get('typed') : null,
            $request->request->has('choice') ? $request->request->getInt('choice') : null,
            new DateTimeImmutable(),
        );
        $request->getSession()->set(self::SESSION_KEY, $session->toArray());

        return $this->redirectToRoute($session->isFinished() ? 'app_plugin_glossary_trainer_results' : 'app_plugin_glossary_trainer_card');
    }

    #[Route('/results', name: 'app_plugin_glossary_trainer_results', methods: ['GET'])]
    public function results(Request $request): Response
    {
        $this->guard();

        $session = $this->session($request);
        if ($session === null) {
            return $this->redirectToRoute('app_plugin_glossary_trainer');
        }

        return $this->renderPage('@Glossary/trainer/results.html.twig', [
            'session' => $session,
            'missed' => $this->trainer->entries($session->missed),
            'streak' => $this->progress->streak($this->userId(), new DateTimeImmutable()),
        ]);
    }

    #[Route('/stop', name: 'app_plugin_glossary_trainer_stop', methods: ['POST'])]
    public function stop(Request $request): Response
    {
        $this->guard();
        $this->assertToken($request, 'glossary_trainer_stop');

        $tagIds = $this->session($request)->tagIds ?? [];
        $request->getSession()->remove(self::SESSION_KEY);

        return $this->redirectToRoute('app_plugin_glossary', $tagIds === [] ? [] : ['tag' => $tagIds]);
    }

    #[Route('/progress', name: 'app_plugin_glossary_trainer_progress', methods: ['GET'])]
    public function progress(Request $request): Response
    {
        $this->guard();

        return $this->renderPage('@Glossary/trainer/progress.html.twig', [
            'progress' => $this->progress->details($this->userId(), $request->getLocale(), new DateTimeImmutable()),
        ]);
    }

    #[Route('/mark/{id}', name: 'app_plugin_glossary_trainer_mark', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function mark(Request $request, int $id): Response
    {
        $this->guard();
        $this->assertToken($request, 'glossary_trainer_mark' . $id);

        if ($this->trainer->toggleMarked($this->userId(), $id, new DateTimeImmutable()) === null) {
            throw $this->createNotFoundException();
        }

        return $this->back($request, $id);
    }

    #[Route('/suspend/{id}', name: 'app_plugin_glossary_trainer_suspend', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function suspend(Request $request, int $id): Response
    {
        $this->guard();
        $this->assertToken($request, 'glossary_trainer_suspend' . $id);

        if ($this->trainer->toggleSuspended($this->userId(), $id, new DateTimeImmutable()) === null) {
            throw $this->createNotFoundException();
        }

        return $this->back($request, $id);
    }

    private function guard(): void
    {
        if (!$this->trainer->isEnabled()) {
            throw $this->createNotFoundException();
        }
    }

    private function userId(): int
    {
        return (int) $this->getAuthedUser()->getId();
    }

    private function session(Request $request): ?TrainerSession
    {
        return TrainerSession::fromArray($request->getSession()->get(self::SESSION_KEY));
    }

    /** @param list<int> $tagIds */
    private function setupForm(Scope $scope, array $tagIds): FormInterface
    {
        $config = $this->trainer->config();
        $directions = $config->getOfferedDirections();

        return $this->createForm(
            TrainerSetupType::class,
            [
                'scope' => $scope,
                'tags' => implode(',', $tagIds),
                'mode' => Mode::Review,
                'direction' => $directions[0],
                'answerMode' => $config->getDefaultAnswerMode(),
                'size' => $config->getSessionSize(),
            ],
            [
                'directions' => $directions,
                'action' => $this->generateUrl('app_plugin_glossary_trainer_start'),
            ],
        );
    }

    /** @param list<int> $tagIds */
    private function renderIndex(Request $request, FormInterface $form, Scope $scope, array $tagIds, int $status = Response::HTTP_OK): Response
    {
        $userId = $this->userId();
        $now = new DateTimeImmutable();

        $response = $this->renderPage('@Glossary/trainer/index.html.twig', [
            'form' => $form,
            'scope' => $scope,
            'tagIds' => $tagIds,
            'scopeCount' => count($this->trainer->scopeIds($scope, $tagIds, $userId, $now)),
            'standingScopes' => $this->trainer->standingScopes($userId, $now),
            'streak' => $this->progress->streak($userId, $now),
            'leaderboard' => $this->progress->leaderboard(),
            'hasOpenSession' => $this->session($request)?->isFinished() === false,
        ]);
        $response->setStatusCode($status);

        return $response;
    }

    private function assertToken(Request $request, string $intention): void
    {
        if (!$this->isCsrfTokenValid($intention, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
    }

    private function back(Request $request, int $id): Response
    {
        if ($request->query->get('return') === 'card') {
            return $this->redirectToRoute('app_plugin_glossary_trainer_card');
        }

        return $this->redirectToRoute('app_plugin_glossary_show', ['id' => $id]);
    }

    /**
     * @param array<mixed> $raw
     * @return list<int>
     */
    private function tagIds(array $raw): array
    {
        $ids = [];
        foreach ($raw as $value) {
            if (!is_numeric($value) || (int) $value <= 0) {
                continue;
            }
            $ids[] = (int) $value;
        }

        return array_values(array_unique($ids));
    }
}
