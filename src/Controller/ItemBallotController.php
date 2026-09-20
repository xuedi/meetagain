<?php declare(strict_types=1);

namespace App\Controller;

use App\Form\BallotTermsType;
use App\Form\ItemBallotType;
use App\Item\Ballot\Candidates;
use App\Item\Ballot\Purpose;
use App\Item\TypeRegistry;
use App\Repository\EventRepository;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotRequest;
use Module\Ballot\Contract\BallotSubject;
use Module\Ballot\Contract\BallotView;
use Module\Ballot\Contract\Candidate;
use Module\Ballot\Contract\SettlementMode;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/item-ballot')]
final class ItemBallotController extends AbstractController
{
    public function __construct(
        private readonly BallotInterface $ballots,
        private readonly Candidates $candidates,
        private readonly EventRepository $eventRepo,
        private readonly TypeRegistry $types,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/create/{eventId}/{itemType}', name: 'app_item_ballot_create', requirements: ['eventId' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function create(int $eventId, string $itemType, Request $request): Response
    {
        $event = $this->eventRepo->find($eventId);
        if ($event === null || !$this->types->has($itemType)) {
            throw $this->createNotFoundException();
        }

        $candidateItemIds = $this->candidates->itemIdsFor($itemType);
        $form = $this->createForm(ItemBallotType::class, null, ['candidateItemIds' => $candidateItemIds]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $picked = array_values(array_map(intval(...), (array) $form->get(ItemBallotType::FIELD_ITEMS)->getData()));

            try {
                $ballotId = $this->ballots->open($this->requestFor($eventId, $event->getTitle($request->getLocale()), $itemType, $picked, $form));

                return $this->redirectToRoute('app_item_ballot_show', ['id' => $ballotId]);
            } catch (InvalidArgumentException) {
                $this->addFlash('danger', 'item_ballot.flash_too_few');
            }
        }

        return $this->render('item/ballot/create.html.twig', [
            'event' => $event,
            'itemType' => $itemType,
            'itemTypeLabelKey' => $this->types->providerFor($itemType)?->getLabelKey(),
            'candidateItemIds' => $candidateItemIds,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_item_ballot_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(int $id): Response
    {
        $ballot = $this->mustView($id);

        return $this->render('item/ballot/show.html.twig', [
            'ballot' => $ballot,
            'itemType' => $this->itemTypeOf($ballot),
        ]);
    }

    #[Route('/{id}/vote', name: 'app_item_ballot_vote', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function vote(int $id, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('item_ballot_vote' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->mustView($id);

        try {
            $this->ballots->cast($id, (int) $this->getAuthedUser()->getId(), $this->submittedKeys($request));
            $this->addFlash('success', 'item_ballot.flash_recorded');
        } catch (DomainException) {
            $this->addFlash('danger', 'item_ballot.flash_closed');
        }

        return $this->redirectToRoute('app_item_ballot_show', ['id' => $id]);
    }

    #[Route('/{id}/close', name: 'app_item_ballot_close', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function close(int $id, Request $request): Response
    {
        $ballot = $this->mustView($id);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('item_ballot_close' . $id, (string) $request->request->get('_token'))) {
                throw new BadRequestHttpException('Invalid CSRF token.');
            }

            try {
                $this->ballots->settle($id, (string) $request->request->get('winner'), (int) $this->getAuthedUser()->getId());
                $this->addFlash('success', 'item_ballot.flash_settled');

                return $this->redirectToRoute('app_item_ballot_show', ['id' => $id]);
            } catch (InvalidArgumentException) {
                $this->addFlash('danger', 'item_ballot.flash_not_a_candidate');
            } catch (DomainException) {
                $this->addFlash('danger', 'item_ballot.flash_resolved');
            }
        }

        return $this->render('item/ballot/close.html.twig', [
            'ballot' => $ballot,
            'itemType' => $this->itemTypeOf($ballot),
            'choices' => $ballot->tiedKeys === []
                ? array_map(static fn(Candidate $candidate): string => $candidate->key, $ballot->candidates)
                : $ballot->tiedKeys,
        ]);
    }

    /**
     * @param list<int> $itemIds
     */
    private function requestFor(int $eventId, string $eventTitle, string $itemType, array $itemIds, FormInterface $form): BallotRequest
    {
        $terms = BallotTermsType::read((array) $form->get(ItemBallotType::FIELD_TERMS)->getData());

        return new BallotRequest(
            Purpose::forType($itemType),
            $this->candidates->forBallot($itemType, $itemIds),
            new DateTimeImmutable($terms['deadline']),
            (int) $this->getAuthedUser()->getId(),
            new BallotSubject(Purpose::SUBJECT_TYPE, $eventId),
            $terms['tallyMode'],
            SettlementMode::Automatic,
            $this->translator->trans('item_ballot.ballot_title', [
                '%type%' => $this->translator->trans((string) $this->types->providerFor($itemType)?->getLabelKey()),
                '%event%' => $eventTitle,
            ]),
        );
    }

    private function mustView(int $id): BallotView
    {
        $ballot = $this->ballots->view($id, (int) $this->getAuthedUser()->getId());
        if ($ballot === null || Purpose::itemTypeOf($ballot->purpose) === null) {
            throw $this->createNotFoundException();
        }

        return $ballot;
    }

    private function itemTypeOf(BallotView $ballot): string
    {
        return (string) Purpose::itemTypeOf($ballot->purpose);
    }

    /**
     * @return list<string>
     */
    private function submittedKeys(Request $request): array
    {
        $submitted = $request->request->all('candidates');

        return array_values(array_map(strval(...), array_filter($submitted, is_scalar(...))));
    }
}
