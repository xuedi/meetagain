<?php declare(strict_types=1);

namespace App\Controller;

use App\Contribution\EventSection;
use App\Contribution\Registry as ContributionRegistry;
use App\Entity\Event;
use App\Entity\User;
use App\Event\ChangeTarget;
use App\Form\EventSuggestionType;
use App\Service\Event\EventService;
use Module\Proposal\Contract\Change\ChangeInterface;
use Module\Proposal\Contract\Change\FieldChange;
use Module\Proposal\Contract\Change\View;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class EventSuggestController extends AbstractController
{
    public const string ROUTE = 'app_event_suggest';

    private const array FIELDS = ['title', 'teaser', 'description'];
    private const string STEP_PREVIEW = 'preview';
    private const string STEP_SUBMIT = 'submit';
    private const string STEP_BACK = 'back';

    public function __construct(
        private readonly ContributionRegistry $contributions,
        private readonly EventService $eventService,
        private readonly ChangeTarget $changeTarget,
        private readonly ChangeInterface $changes,
    ) {}

    #[Route('/event/{id}/suggest', name: self::ROUTE, requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function suggest(Request $request, int $id, #[CurrentUser] User $user): Response
    {
        $event = $this->eventService->findOneForDetails($id);
        if (!$event instanceof Event || !$this->contributions->mayTouch(EventSection::TYPE, $user, $id)) {
            throw $this->createNotFoundException();
        }

        $locale = $request->getLocale();
        if ($event->findTranslation($locale) === null) {
            return $this->redirectToRoute('app_event_details', ['id' => $id]);
        }

        $current = $this->currentValues($event, $locale);
        $applyScope = $this->changeTarget->getApplyScope($id);
        $form = $this->createForm(EventSuggestionType::class, $current, ['series' => $applyScope !== null]);
        $form->handleRequest($request);

        $step = $request->request->getString('step');
        $proposed = $form->isSubmitted() && $form->isValid() ? $this->proposedChanges($form, $current, $locale) : [];
        if ($form->isSubmitted() && $form->isValid() && $proposed === [] && $step !== self::STEP_BACK) {
            $this->addFlash('info', 'events.suggest_flash_unchanged');
        }

        if ($proposed !== [] && $step === self::STEP_SUBMIT) {
            $seriesRequested = $applyScope !== null && $form->has('series') && $form->get('series')->getData() === true;
            $this->changes->propose(
                ChangeTarget::TARGET_TYPE,
                $id,
                (int) $user->getId(),
                $proposed,
                (string) $form->get('reason')->getData(),
                $seriesRequested ? $applyScope['key'] : null,
            );
            $this->addFlash('success', 'events.suggest_flash_sent');
            $this->addFlash('action', 'event-suggest-success');

            return $this->redirectToRoute(self::ROUTE, ['id' => $id]);
        }

        return $this->render('events/suggest.html.twig', [
            'event' => $event,
            'form' => $form,
            'current' => $current,
            'preview' => $proposed !== [] && $step === self::STEP_PREVIEW ? $this->previewRows($proposed) : null,
            'seriesRequested' => $form->has('series') && $form->get('series')->getData() === true,
            'applyScope' => $applyScope,
            'pendingCards' => $this->ownPendingCards($id, $user),
        ]);
    }

    /**
     * @return array<string, ?string>
     */
    private function currentValues(Event $event, string $locale): array
    {
        $values = [];
        foreach (self::FIELDS as $property) {
            $values[$property] = $this->changeTarget->currentValue($event, $property . '_' . $locale);
        }

        return $values;
    }

    /**
     * @param  FormInterface           $form
     * @param  array<string, ?string> $current
     * @return list<FieldChange>
     */
    private function proposedChanges(FormInterface $form, array $current, string $locale): array
    {
        $changes = [];
        foreach (self::FIELDS as $property) {
            $before = $current[$property] ?? null;
            $after = $form->get($property)->getData();
            $after = $after === null ? null : str_replace("\r\n", "\n", (string) $after);
            if ((string) $before === (string) $after) {
                continue;
            }

            $changes[] = new FieldChange($property . '_' . $locale, $before, $after);
        }

        return $changes;
    }

    /**
     * @param  list<FieldChange> $changes
     * @return list<array{label: string, before: string, after: string}>
     */
    private function previewRows(array $changes): array
    {
        return array_map(fn(FieldChange $change): array => [
            'label' => $this->changeTarget->getFieldLabel($change->field),
            'before' => $this->changeTarget->formatValue($change->field, $change->before),
            'after' => $this->changeTarget->formatValue($change->field, $change->after),
        ], $changes);
    }

    /**
     * @return list<array{proposal: View, rows: list<array<string, mixed>>, isProposer: bool}>
     */
    private function ownPendingCards(int $id, User $user): array
    {
        $cards = [];
        foreach ($this->changes->pendingFor(ChangeTarget::TARGET_TYPE, $id) as $proposal) {
            if ($proposal->proposerId === $user->getId()) {
                $cards[] = ['proposal' => $proposal, 'rows' => $proposal->rows, 'isProposer' => true];
            }
        }

        return $cards;
    }
}
