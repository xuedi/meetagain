<?php declare(strict_types=1);

namespace App\Controller;

use App\Event\BallotLocationChoice;
use App\Filter\Admin\Event\AdminEventListFilterService;
use DomainException;
use InvalidArgumentException;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/venue-ballot')]
final class VenueBallotController extends AbstractController
{
    public function __construct(
        private readonly BallotInterface $ballots,
        private readonly AdminEventListFilterService $eventFilterService,
    ) {}

    #[Route('/{id}/close', name: 'app_venue_ballot_close', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function close(int $id, Request $request): Response
    {
        $ballot = $this->mustView($id);
        if ($ballot->subject?->type !== BallotLocationChoice::SUBJECT_TYPE || !$this->eventFilterService->isEventAccessible($ballot->subject->id)) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('venue_ballot_close' . $id, (string) $request->request->get('_token'))) {
                throw new BadRequestHttpException('Invalid CSRF token.');
            }

            try {
                $this->ballots->settle($id, (string) $request->request->get('winner'), (int) $this->getAuthedUser()->getId());
                $this->addFlash('success', 'venue_ballot.flash_settled');
                $this->addFlash('action', 'venue-settle-success');

                return $this->redirectToRoute('app_ballot_show', ['id' => $id]);
            } catch (InvalidArgumentException) {
                $this->addFlash('danger', 'venue_ballot.flash_not_a_candidate');
            } catch (DomainException) {
                $this->addFlash('danger', 'venue_ballot.flash_resolved');
            }
        }

        return $this->render('venue/ballot/close.html.twig', [
            'ballot' => $ballot,
            'choices' => $this->choices($ballot),
        ]);
    }

    private function mustView(int $id): BallotView
    {
        $ballot = $this->ballots->view($id, (int) $this->getAuthedUser()->getId());
        if ($ballot === null || $ballot->purpose !== BallotLocationChoice::PURPOSE) {
            throw $this->createNotFoundException();
        }

        return $ballot;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function choices(BallotView $ballot): array
    {
        $choices = [];
        foreach ($ballot->candidates as $candidate) {
            if ($ballot->tiedKeys !== [] && !in_array($candidate->key, $ballot->tiedKeys, true)) {
                continue;
            }

            $choices[] = ['key' => $candidate->key, 'label' => $candidate->label];
        }

        return $choices;
    }
}
