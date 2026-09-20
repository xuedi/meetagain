<?php declare(strict_types=1);

namespace App\Event;

use App\Entity\Event;
use App\Entity\Location;
use App\Entity\User;
use App\Filter\Admin\Location\AdminLocationListFilterService;
use App\Form\BallotTermsType;
use App\Repository\LocationRepository;
use DateTimeImmutable;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotRequest;
use Module\Ballot\Contract\BallotStatus;
use Module\Ballot\Contract\BallotSubject;
use Module\Ballot\Contract\BallotView;
use Module\Ballot\Contract\Candidate;
use Module\Ballot\Contract\SettlementMode;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class BallotLocationChoice implements LocationChoiceProviderInterface
{
    public const string VALUE = 'ballot:location';
    public const string PURPOSE = 'event.location';
    public const string SUBJECT_TYPE = 'event';
    public const string CANDIDATE_UNDECIDED = 'undecided';

    public function __construct(
        private BallotInterface $ballots,
        private LocationRepository $locationRepo,
        private AdminLocationListFilterService $locationFilter,
        private Security $security,
        private TranslatorInterface $translator,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return '';
    }

    #[Override]
    public function getValue(): string
    {
        return self::VALUE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'admin_event.venue_option_vote';
    }

    #[Override]
    public function isAvailableFor(?Event $event): bool
    {
        return $this->venues() !== [];
    }

    #[Override]
    public function isActiveFor(Event $event): bool
    {
        return $this->runningBallot($event) !== null;
    }

    #[Override]
    public function choose(Event $event, array $terms): void
    {
        $userId = $this->currentUserId();
        if ($event->getId() === null || $userId === null || $this->runningBallot($event) !== null) {
            return;
        }

        $settings = BallotTermsType::read($terms);
        $this->ballots->open(
            new BallotRequest(
                self::PURPOSE,
                $this->candidates(),
                new DateTimeImmutable($settings['deadline']),
                $userId,
                new BallotSubject(self::SUBJECT_TYPE, (int) $event->getId()),
                $settings['tallyMode'],
                SettlementMode::Automatic,
                $this->translator->trans('admin_event.venue_ballot_title', ['%event%' => $this->titleOf($event)]),
            ),
        );
    }

    #[Override]
    public function release(Event $event): void
    {
        $running = $this->runningBallot($event);
        if ($running === null) {
            return;
        }

        $this->ballots->abandon($running->id, $this->currentUserId());
    }

    public function pendingDecisionFor(Event $event): ?BallotView
    {
        foreach ($this->ballotsFor($event) as $view) {
            if ($view->purpose === self::PURPOSE && $view->status === BallotStatus::Tallied) {
                return $view;
            }
        }

        return null;
    }

    private function currentUserId(): ?int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? (int) $user->getId() : null;
    }

    private function runningBallot(Event $event): ?BallotView
    {
        foreach ($this->ballotsFor($event) as $view) {
            if ($view->purpose === self::PURPOSE && !$view->status->isResolved()) {
                return $view;
            }
        }

        return null;
    }

    /**
     * @return list<BallotView>
     */
    private function ballotsFor(Event $event): array
    {
        $eventId = $event->getId();

        return $eventId === null ? [] : $this->ballots->listForSubject(new BallotSubject(self::SUBJECT_TYPE, $eventId), null);
    }

    /**
     * @return list<Candidate>
     */
    private function candidates(): array
    {
        $candidates = [];
        foreach ($this->venues() as $venue) {
            $candidates[] = new Candidate((string) $venue->getId(), (string) $venue->getName());
        }

        $candidates[] = new Candidate(self::CANDIDATE_UNDECIDED, $this->translator->trans('admin_event.venue_ballot_undecided'));

        return $candidates;
    }

    /**
     * @return list<Location>
     */
    private function venues(): array
    {
        return array_values($this->locationRepo->findAllForAdmin($this->locationFilter->getLocationIdFilter()->getLocationIds()));
    }

    private function titleOf(Event $event): string
    {
        foreach ($event->getTranslation() as $translation) {
            $title = $translation->getTitle();
            if ($title !== null && $title !== '') {
                return $title;
            }
        }

        return '#' . $event->getId();
    }
}
