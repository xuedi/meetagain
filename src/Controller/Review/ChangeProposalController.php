<?php declare(strict_types=1);

namespace App\Controller\Review;

use App\Controller\AbstractController;
use App\Entity\ChangeProposal;
use App\Entity\User;
use App\Review\ChangeProposalException;
use App\Review\ChangeProposalService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/review')]
#[IsGranted('ROLE_USER')]
final class ChangeProposalController extends AbstractController
{
    public function __construct(
        private readonly ChangeProposalService $service,
    ) {}

    #[Route('/proposals/{targetType}/{targetId}', name: 'app_review_proposals', methods: ['GET'], requirements: ['targetId' => '\d+'])]
    public function proposals(string $targetType, int $targetId, #[CurrentUser] User $user): Response
    {
        $targetLabel = $this->service->targetLabel($targetType, $targetId);
        if ($targetLabel === null) {
            throw $this->createNotFoundException();
        }

        $proposals = $this->service->pendingForTarget($targetType, $targetId);
        $canReview = $this->service->canReviewTarget($targetType, $targetId, $user);
        $cards = [];
        $isProposerOfAny = false;
        foreach ($proposals as $proposal) {
            $isProposer = $proposal->getProposedBy()->getId() === $user->getId();
            $isProposerOfAny = $isProposerOfAny || $isProposer;
            $cards[] = [
                'proposal' => $proposal,
                'rows' => $this->service->fieldRows($proposal),
                'isProposer' => $isProposer,
            ];
        }

        if (!$canReview && !$isProposerOfAny) {
            throw $this->createNotFoundException();
        }

        return $this->render('review/proposals.html.twig', [
            'targetLabel' => $targetLabel,
            'targetUrl' => $this->service->targetUrl($targetType, $targetId),
            'cards' => $cards,
            'canReview' => $canReview,
        ]);
    }

    #[Route('/proposal/{id}/apply/{field}', name: 'app_review_proposal_apply', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function applyField(Request $request, int $id, string $field, #[CurrentUser] User $user): Response
    {
        $proposal = $this->pendingProposal($request, $id);

        try {
            $this->service->applyField($proposal, $field, $user);
            $this->addFlash('success', 'review.flash_field_applied');
        } catch (ChangeProposalException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToProposals($proposal);
    }

    #[Route('/proposal/{id}/deny/{field}', name: 'app_review_proposal_deny', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function denyField(Request $request, int $id, string $field, #[CurrentUser] User $user): Response
    {
        $proposal = $this->pendingProposal($request, $id);

        try {
            $this->service->denyField($proposal, $field, $user);
            $this->addFlash('success', 'review.flash_field_denied');
        } catch (ChangeProposalException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToProposals($proposal);
    }

    #[Route('/proposal/{id}/withdraw', name: 'app_review_proposal_withdraw', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function withdraw(Request $request, int $id, #[CurrentUser] User $user): Response
    {
        $proposal = $this->pendingProposal($request, $id);

        try {
            $this->service->withdraw($proposal, $user);
            $this->addFlash('success', 'review.flash_withdrawn');
        } catch (ChangeProposalException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $targetUrl = $this->service->targetUrl($proposal->getTargetType(), $proposal->getTargetId());
        if ($targetUrl !== null) {
            return $this->redirect($targetUrl);
        }

        return $this->redirectToProposals($proposal);
    }

    private function pendingProposal(Request $request, int $id): ChangeProposal
    {
        if (!$this->isCsrfTokenValid('change_proposal' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $proposal = $this->service->get($id);
        if ($proposal === null) {
            throw $this->createNotFoundException();
        }

        return $proposal;
    }

    private function redirectToProposals(ChangeProposal $proposal): Response
    {
        return $this->redirectToRoute('app_review_proposals', [
            'targetType' => $proposal->getTargetType(),
            'targetId' => $proposal->getTargetId(),
        ]);
    }
}
