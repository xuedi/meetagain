<?php declare(strict_types=1);

namespace Module\Suggestion\Internal\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use Module\Suggestion\Internal\Entity\Suggestion;
use Module\Suggestion\Internal\SuggestionException;
use Module\Suggestion\Internal\SuggestionService;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/review/suggestions/{id}', requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_USER')]
final class SuggestionController extends AbstractController
{
    public function __construct(
        private readonly SuggestionService $service,
    ) {}

    #[Route('', name: 'app_review_suggestion', methods: ['GET'])]
    public function detail(int $id, #[CurrentUser] User $user): Response
    {
        $suggestion = $this->visibleSuggestion($id, $user);

        return $this->renderDetail($suggestion, $user, $this->buildForm($suggestion));
    }

    #[Route('/approve', name: 'app_review_suggestion_approve', methods: ['POST'])]
    public function approve(Request $request, int $id, #[CurrentUser] User $user): Response
    {
        $suggestion = $this->visibleSuggestion($id, $user);
        if (!$this->canReview($suggestion, $user)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->buildForm($suggestion);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderDetail($suggestion, $user, $form);
        }

        try {
            $this->service->approve($suggestion, $form->getData(), $user);
        } catch (SuggestionException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->renderDetail($suggestion, $user, $form);
        }

        $this->addFlash('success', 'review_suggestion.flash_approved');

        return $this->redirectToRoute('app_profile_review');
    }

    #[Route('/reject', name: 'app_review_suggestion_reject', methods: ['POST'])]
    public function reject(Request $request, int $id, #[CurrentUser] User $user): Response
    {
        $suggestion = $this->guardedSuggestion($request, $id, $user);

        try {
            $this->service->reject($suggestion, $user);
            $this->addFlash('success', 'review_suggestion.flash_rejected');
        } catch (SuggestionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_profile_review');
    }

    #[Route('/withdraw', name: 'app_review_suggestion_withdraw', methods: ['POST'])]
    public function withdraw(Request $request, int $id, #[CurrentUser] User $user): Response
    {
        $suggestion = $this->guardedSuggestion($request, $id, $user);

        try {
            $this->service->withdraw($suggestion, $user);
            $this->addFlash('success', 'review_suggestion.flash_withdrawn');
        } catch (SuggestionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_review_suggestion', ['id' => $id]);
    }

    private function guardedSuggestion(Request $request, int $id, User $user): Suggestion
    {
        if (!$this->isCsrfTokenValid('suggestion' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        return $this->visibleSuggestion($id, $user);
    }

    private function visibleSuggestion(int $id, User $user): Suggestion
    {
        $suggestion = $this->service->get($id);
        if ($suggestion === null || !$this->service->hasProvider($suggestion->getTargetType())) {
            throw $this->createNotFoundException();
        }

        $isReviewer = $this->canReview($suggestion, $user);
        $isProposer = $this->isProposer($suggestion, $user);
        if (!$isReviewer && !$isProposer) {
            throw $this->createNotFoundException();
        }

        return $suggestion;
    }

    private function canReview(Suggestion $suggestion, User $user): bool
    {
        return $this->service->canReviewTargetType($suggestion->getTargetType(), $user);
    }

    private function isProposer(Suggestion $suggestion, User $user): bool
    {
        return $suggestion->getProposedBy()->getId() === $user->getId();
    }

    private function buildForm(Suggestion $suggestion): FormInterface
    {
        $provider = $this->service->requireProvider($suggestion->getTargetType());

        return $this->createForm($provider->getFormType(), $provider->fromPayload($suggestion->getPayload()));
    }

    private function renderDetail(Suggestion $suggestion, User $user, FormInterface $form): Response
    {
        $provider = $this->service->requireProvider($suggestion->getTargetType());

        return $this->render('@Suggestion/review.html.twig', [
            'suggestion' => $suggestion,
            'form' => $form,
            'canReview' => $this->canReview($suggestion, $user),
            'isProposer' => $this->isProposer($suggestion, $user),
            'labelKey' => $provider->getLabelKey(),
            'rows' => $this->service->summaryRows($suggestion),
        ]);
    }
}
