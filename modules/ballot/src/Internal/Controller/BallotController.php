<?php declare(strict_types=1);

namespace Module\Ballot\Internal\Controller;

use App\Controller\AbstractController;
use DomainException;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotView;
use Module\Ballot\Contract\PageProviderInterface;
use Module\Ballot\Contract\TallyMode;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER'), Route('/ballots')]
final class BallotController extends AbstractController
{
    /**
     * @param iterable<PageProviderInterface> $pageProviders
     */
    public function __construct(
        private readonly BallotInterface $ballots,
        #[AutowireIterator(PageProviderInterface::class)]
        private readonly iterable $pageProviders,
    ) {}

    #[Route('', name: 'app_ballot_index', methods: ['GET'])]
    public function index(): Response
    {
        $ballots = $this->ballots->listOpenFor($this->viewerId());

        return $this->render('@Ballot/index.html.twig', [
            'ballots' => $ballots,
            'urls' => array_map(
                fn(BallotView $ballot): string => $this->claimedUrl($ballot) ?? $this->generateUrl('app_ballot_show', ['id' => $ballot->id]),
                $ballots,
            ),
        ]);
    }

    #[Route('/{id}', name: 'app_ballot_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $ballot = $this->ballots->view($id, $this->viewerId());
        if ($ballot === null) {
            throw $this->createNotFoundException();
        }

        $claimedUrl = $this->claimedUrl($ballot);
        if ($claimedUrl !== null) {
            return $this->redirect($claimedUrl);
        }

        return $this->render('@Ballot/show.html.twig', [
            'ballot' => $ballot,
            'singleChoice' => $ballot->tallyMode === TallyMode::Single,
        ]);
    }

    #[Route('/{id}/vote', name: 'app_ballot_vote', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function vote(int $id, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('ballot_vote' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $viewerId = $this->viewerId();
        if ($this->ballots->view($id, $viewerId) === null) {
            throw $this->createNotFoundException();
        }

        try {
            $this->ballots->cast($id, $viewerId, $this->submittedKeys($request));
            $this->addFlash('success', 'ballot.flash_recorded');
        } catch (DomainException) {
            $this->addFlash('danger', 'ballot.flash_closed');
        }

        return $this->redirectToRoute('app_ballot_show', ['id' => $id]);
    }

    /**
     * @return list<string>
     */
    private function submittedKeys(Request $request): array
    {
        $submitted = $request->request->all('candidates');

        return array_values(array_map(strval(...), array_filter($submitted, is_scalar(...))));
    }

    private function claimedUrl(BallotView $ballot): ?string
    {
        foreach ($this->pageProviders as $provider) {
            if ($provider->supports($ballot->purpose)) {
                return $provider->url($ballot);
            }
        }

        return null;
    }

    private function viewerId(): int
    {
        return (int) $this->getAuthedUser()->getId();
    }
}
