<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Activity\ActivityService;
use App\Activity\Messages\AdminEventCancelled;
use App\Activity\Messages\AdminEventCreated;
use App\Activity\Messages\AdminEventEdited;
use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Emails\Types\EventUpdateNotificationEmail;
use App\Emails\Types\SeriesRescheduledEmail;
use App\Entity\Event;
use App\Entity\EventSeries;
use App\Entity\EventTranslation;
use App\Entity\Host;
use App\Entity\Image;
use App\Entity\Location;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Enum\EventInterval;
use App\Enum\EventType as EventTypeEnum;
use App\Enum\ImageType;
use App\Enum\RecurrenceMode;
use App\Enum\RecurrenceOrdinal;
use App\Enum\RecurrencePeriod;
use App\Enum\Weekday;
use App\Exception\Event\InvalidRecurrencePatternException;
use App\Filter\Admin\Event\AdminEventListFilterService;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Repository\EventTranslationRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Config\LanguageService;
use App\Service\Event\EventService;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use App\Service\Event\RecurrenceBuilderStateResolver;
use App\Service\Event\RecurrenceDescriber;
use App\Service\Event\RecurrencePreviewService;
use App\Service\Event\RecurrenceResolver;
use App\Service\Seo\EventCanonicalRebuildService;
use App\ValueObject\RecurrenceBuilderState;
use App\ValueObject\ScheduleChange;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ORGANIZER'), Route('/admin/events')]
final class EventController extends AbstractController implements AdminNavigationInterface
{
    private const string DEFAULT_RANGE = 'all';

    /** @var array<string, string|null> Forward window applied to the event start; null means no limit. */
    private const array RANGE_OFFSETS = [
        'all' => null,
        '1y' => '+1 year',
        '1m' => '+1 month',
        '1w' => '+1 week',
    ];

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_content',
            links: [
                new AdminLink(label: 'admin_shell.menu_event', route: 'app_admin_event', active: 'event', role: 'ROLE_ORGANIZER'),
            ],
            sectionPriority: 50,
        );
    }

    public function __construct(
        private readonly ImageService $imageService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LanguageService $languageService,
        private readonly EventTranslationRepository $eventTransRepo,
        private readonly EventService $eventService,
        private readonly EventRepository $repo,
        private readonly AdminEventListFilterService $eventFilterService,
        private readonly EntityActionDispatcher $entityActionDispatcher,
        private readonly ActivityService $activityService,
        private readonly ImageLocationService $imageLocationService,
        private readonly TranslatorInterface $translator,
        private readonly EventUpdateNotificationEmail $eventUpdateNotificationEmail,
        private readonly SeriesRescheduledEmail $seriesRescheduledEmail,
        private readonly HtmlSanitizerInterface $cmsContent,
        private readonly EventCanonicalRebuildService $canonicalRebuildService,
        private readonly RecurrencePreviewService $recurrencePreviewService,
        private readonly RecurrenceResolver $recurrenceResolver,
        private readonly RecurrenceDescriber $recurrenceDescriber,
        private readonly RecurrenceBuilderStateResolver $recurrenceBuilderStateResolver,
    ) {}

    #[Route('/recurrence/preview', name: 'app_admin_event_recurrence_preview', methods: ['GET'])]
    public function recurrencePreview(Request $request): JsonResponse
    {
        $mode = RecurrenceMode::tryFrom((string) $request->query->get('mode'));
        $period = RecurrencePeriod::tryFrom((string) $request->query->get('period'));
        $after = DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->query->get('after'))
            ?: new DateTimeImmutable('today');

        if (!$mode instanceof RecurrenceMode || !$period instanceof RecurrencePeriod) {
            return $this->json(['error' => 'invalid_parameters'], Response::HTTP_BAD_REQUEST);
        }

        $query = $request->query->all();
        $state = $this->recurrenceBuilderStateResolver->resolve(
            mode: $mode,
            period: $period,
            ordinals: array_values(array_filter(array_map(
                static fn(mixed $value): ?RecurrenceOrdinal => RecurrenceOrdinal::tryFrom((int) $value),
                (array) ($query['ordinal'] ?? []),
            ))),
            weekdays: array_values(array_filter(array_map(
                static fn(mixed $value): ?Weekday => Weekday::tryFrom((string) $value),
                (array) ($query['weekday'] ?? []),
            ))),
            daysOfMonth: array_values(array_map(
                static fn(mixed $value): int => (int) $value,
                (array) ($query['day'] ?? []),
            )),
            fallbackWeekday: Weekday::fromDate($after),
        );

        try {
            $candidates = $this->recurrencePreviewService->candidates($state, $after, $request->getLocale());
        } catch (InvalidRecurrencePatternException) {
            return $this->json(['error' => 'invalid_parameters'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->recurrenceStatePayload($state) + ['candidates' => $candidates]);
    }

    /**
     * @return array{
     *     selection: array{mode: string, period: string, ordinal: list<int>, weekday: list<string>, day: list<int>},
     *     controls: array{ordinal: bool, weekday: bool, weekdayMultiple: bool, day: bool, multiHint: bool, shortMonthHint: bool, periods: list<string>}
     * }
     */
    private function recurrenceStatePayload(RecurrenceBuilderState $state): array
    {
        return [
            'selection' => [
                'mode' => $state->mode->value,
                'period' => $state->period->value,
                'ordinal' => array_map(static fn(RecurrenceOrdinal $case): int => $case->value, $state->ordinals),
                'weekday' => array_map(static fn(Weekday $case): string => $case->value, $state->weekdays),
                'day' => $state->daysOfMonth,
            ],
            'controls' => [
                'ordinal' => $state->showsOrdinal(),
                'weekday' => $state->showsWeekday(),
                'weekdayMultiple' => $state->allowsSeveralWeekdays(),
                'day' => $state->showsDayOfMonth(),
                'multiHint' => $state->allowsSeveralEntries(),
                'shortMonthHint' => $state->warnsAboutShortMonths(),
                'periods' => array_map(static fn(RecurrencePeriod $case): string => $case->value, $state->periods),
            ],
        ];
    }

    #[Route('', name: 'app_admin_event')]
    public function list(Request $request): Response
    {
        $filterResult = $this->eventFilterService->getEventIdFilter();
        $eventIds = $filterResult->getEventIds();
        $allEvents = $this->repo->findAllForAdmin($eventIds);

        $range = $request->query->getString('range', self::DEFAULT_RANGE);
        if (!array_key_exists($range, self::RANGE_OFFSETS)) {
            $range = self::DEFAULT_RANGE;
        }
        $until = $this->rangeUntil($range);
        $typeFilter = $request->query->getInt('type') ?: null;
        $scheduleFilter = $request->query->getString('schedule');
        if (!in_array($scheduleFilter, ['onetime', 'series'], true)) {
            $scheduleFilter = 'all';
        }

        $events = array_values(array_filter($allEvents, fn(Event $e) => $this->matchesFilters($e, $until, $typeFilter, $scheduleFilter)));

        $canceledCount = 0;
        foreach ($events as $event) {
            if (!$event->isCanceled()) {
                continue;
            }
            $canceledCount++;
        }

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', count($events), $this->translator->trans('admin_event.summary_total'))),
        ];
        if (count($events) !== count($allEvents)) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($allEvents),
                $this->translator->trans('admin_event.summary_total_all'),
            ));
        }
        if ($canceledCount > 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-warning is-medium"><strong>%d</strong>&nbsp;%s</span>',
                $canceledCount,
                $this->translator->trans('admin_event.summary_canceled'),
            ));
        }

        $actions = [
            $this->buildRangeDropdown($allEvents, $range, $typeFilter, $scheduleFilter),
            $this->buildTypeDropdown($allEvents, $until, $range, $typeFilter, $scheduleFilter),
            $this->buildScheduleDropdown($allEvents, $until, $range, $typeFilter, $scheduleFilter),
            new AdminTopActionButton(
                label: $this->translator->trans('admin_event.page_title_new'),
                target: $this->generateUrl('app_admin_event_add'),
                icon: 'plus',
            ),
        ];

        return $this->render('admin/event/list.html.twig', [
            'nextEvent' => $this->repo->getNextEventId($eventIds),
            'events' => $events,
            'rsvpCounts' => $this->repo->getRsvpCounts($eventIds),
            'active' => 'event',
            'adminTop' => new AdminTop(info: $info, actions: $actions),
        ]);
    }

    private function matchesFilters(Event $e, ?DateTimeImmutable $until, ?int $typeFilter, string $scheduleFilter): bool
    {
        if ($until !== null && $e->getStart() > $until) {
            return false;
        }
        if ($typeFilter !== null && $e->getType()?->value !== $typeFilter) {
            return false;
        }
        if ($scheduleFilter === 'onetime' && $e->getSeries() !== null) {
            return false;
        }
        if ($scheduleFilter === 'series' && $e->getSeries() === null) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, int|string|bool> URL params preserving every active filter except $exclude.
     */
    private function preserveFiltersExcept(string $exclude, string $range, ?int $typeFilter, string $scheduleFilter): array
    {
        $p = [];
        if ($exclude !== 'range' && $range !== self::DEFAULT_RANGE) {
            $p['range'] = $range;
        }
        if ($exclude !== 'type' && $typeFilter !== null) {
            $p['type'] = $typeFilter;
        }
        if ($exclude !== 'schedule' && $scheduleFilter !== 'all') {
            $p['schedule'] = $scheduleFilter;
        }

        return $p;
    }

    private function rangeUntil(string $range): ?DateTimeImmutable
    {
        $offset = self::RANGE_OFFSETS[$range] ?? null;

        return $offset !== null ? new DateTimeImmutable($offset) : null;
    }

    /**
     * @param array<Event> $allEvents
     */
    private function buildRangeDropdown(array $allEvents, string $range, ?int $typeFilter, string $scheduleFilter): AdminTopActionDropdown
    {
        $options = [];
        $activeLabel = '';
        foreach (array_keys(self::RANGE_OFFSETS) as $key) {
            $optionUntil = $this->rangeUntil($key);
            $count = count(array_filter($allEvents, fn(Event $e) => $this->matchesFilters($e, $optionUntil, $typeFilter, $scheduleFilter)));
            $params = $this->preserveFiltersExcept('range', $range, $typeFilter, $scheduleFilter);
            if ($key !== self::DEFAULT_RANGE) {
                $params['range'] = $key;
            }
            $label = $this->translator->trans('admin_event.filter_range_' . $key);
            $isActive = $range === $key;
            if ($isActive) {
                $activeLabel = $label;
            }
            $options[] = new AdminTopActionDropdownOption(
                label: $label,
                target: $this->generateUrl('app_admin_event', $params),
                isActive: $isActive,
                count: $count,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf('%s %s', $this->translator->trans('admin_event.filter_range_label'), $activeLabel),
            options: $options,
            icon: 'clock',
        );
    }

    /**
     * @param array<Event> $allEvents
     */
    private function buildTypeDropdown(
        array $allEvents,
        ?DateTimeImmutable $until,
        string $range,
        ?int $typeFilter,
        string $scheduleFilter,
    ): AdminTopActionDropdown {
        $countAll = count(array_filter($allEvents, fn(Event $e) => $this->matchesFilters($e, $until, null, $scheduleFilter)));

        $options = [
            new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_event.filter_type_any'),
                target: $this->generateUrl('app_admin_event', $this->preserveFiltersExcept('type', $range, $typeFilter, $scheduleFilter)),
                isActive: $typeFilter === null,
                count: $countAll,
            ),
        ];

        $activeLabel = $this->translator->trans('admin_event.filter_type_any');
        foreach (EventTypeEnum::cases() as $case) {
            $count = count(array_filter($allEvents, fn(Event $e) => $this->matchesFilters($e, $until, $case->value, $scheduleFilter)));
            $params = $this->preserveFiltersExcept('type', $range, $typeFilter, $scheduleFilter);
            $params['type'] = $case->value;
            $label = $this->translator->trans('admin_event.filter_type_' . strtolower($case->name));
            $isActive = $typeFilter === $case->value;
            if ($isActive) {
                $activeLabel = $label;
            }
            $options[] = new AdminTopActionDropdownOption(
                label: $label,
                target: $this->generateUrl('app_admin_event', $params),
                isActive: $isActive,
                count: $count,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf('%s %s', $this->translator->trans('admin_event.filter_type_label'), $activeLabel),
            options: $options,
            icon: 'tag',
        );
    }

    /**
     * @param array<Event> $allEvents
     */
    private function buildScheduleDropdown(
        array $allEvents,
        ?DateTimeImmutable $until,
        string $range,
        ?int $typeFilter,
        string $scheduleFilter,
    ): AdminTopActionDropdown {
        $values = ['all', 'onetime', 'series'];
        $options = [];
        $activeLabel = '';
        foreach ($values as $value) {
            $count = count(array_filter($allEvents, fn(Event $e) => $this->matchesFilters($e, $until, $typeFilter, $value)));
            $params = $this->preserveFiltersExcept('schedule', $range, $typeFilter, $scheduleFilter);
            if ($value !== 'all') {
                $params['schedule'] = $value;
            }
            $label = $this->translator->trans('admin_event.filter_schedule_' . $value);
            $isActive = $scheduleFilter === $value;
            if ($isActive) {
                $activeLabel = $label;
            }
            $options[] = new AdminTopActionDropdownOption(
                label: $label,
                target: $this->generateUrl('app_admin_event', $params),
                isActive: $isActive,
                count: $count,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf('%s %s', $this->translator->trans('admin_event.filter_schedule_label'), $activeLabel),
            options: $options,
            icon: 'calendar',
        );
    }

    #[Route('/{id}/edit', name: 'app_admin_event_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Event $event): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::EVENT_UPDATE, $event);

        if (!$this->eventFilterService->isEventAccessible($event->getId())) {
            throw $this->createAccessDeniedException('This event is not accessible in the current context');
        }

        $form = $this->createForm(EventType::class, $event);

        if ($request->isMethod('GET')) {
            $form->get('location')->setData($event->getLocation());
            $form->get('host')->setData($event->getHost());
        }

        $beforeSnapshot = $this->captureEventSnapshot($event);
        $oldStart = DateTimeImmutable::createFromInterface($event->getStart());
        $oldStop = $event->getStop() !== null ? DateTimeImmutable::createFromInterface($event->getStop()) : null;
        $oldRule = $event->getSeries()?->getRule();
        $oldRuleSpec = $event->getSeries()?->getRuleSpec();

        // TODO: simplify with vanilla symfony components now the cascading flush effect is fixed
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid() && !$request->request->has('reschedule_cancel')) {
            $user = $this->getAuthedUser();

            $isSeries = $event->getSeries() !== null;
            $newRule = $form->get('seriesRule')->getData();
            $newRuleSpec = $this->resolveSubmittedRuleSpec($form, $newRule);
            $seriesName = trim((string) $form->get('seriesName')->getData());
            $ruleChanged = $oldRule !== $newRule || $oldRuleSpec !== $newRuleSpec;

            if (!$isSeries && !$this->validateSeriesName($form)) {
                $this->entityManager->refresh($event);

                return $this->renderEditPage($event, $form);
            }

            $change = new ScheduleChange(
                oldStart: $oldStart,
                oldStop: $oldStop,
                oldRule: $oldRule,
                oldRuleSpec: $oldRuleSpec,
                newStart: DateTimeImmutable::createFromInterface($event->getStart()),
                newStop: $event->getStop() !== null ? DateTimeImmutable::createFromInterface($event->getStop()) : null,
                newRule: $newRule,
                newRuleSpec: $newRuleSpec,
            );
            $wantsRealign = $form->get('allFollowing')->getData() === true && $isSeries && $change->isChanged();
            $ruleChangeForcesConfirm = $isSeries && $ruleChanged;

            if (($wantsRealign || $ruleChangeForcesConfirm) && !$request->request->has('reschedule_confirm')) {
                $plan = $this->eventService->planRealignment($event, $change);
                if (!$plan->isEmpty() || $ruleChangeForcesConfirm) {
                    $pendingImageDropped = $form->get('image')->getData() instanceof UploadedFile;
                    $this->entityManager->refresh($event);

                    return $this->render('admin/event/reschedule_confirm.html.twig', [
                        'active' => 'event',
                        'event' => $event,
                        'plan' => $plan,
                        'change' => $change,
                        'seriesClosing' => $isSeries && $newRule === null,
                        'payload' => $request->request->all(),
                        'pendingImageDropped' => $pendingImageDropped,
                    ]);
                }
            }

            $event->setInitial(true);
            $event->setUser($user);

            if ($isSeries) {
                $series = $event->getSeries();
                if ($seriesName !== '') {
                    $series->setName($seriesName);
                }
                if ($ruleChanged) {
                    $series->setRule($newRule);
                    $series->setRuleSpec($newRuleSpec);
                }
            } elseif ($newRule instanceof EventInterval) {
                $event->setSeries($this->createSeries($seriesName, $newRule, $newRuleSpec));
            }

            $locationData = $form->get('location')->getData();
            if ($locationData instanceof Location) {
                $event->setLocation($locationData);
            }

            $event->getHost()->clear();
            $hostsData = $form->get('host')->getData();
            if (is_iterable($hostsData)) {
                foreach ($hostsData as $host) {
                    if (!$host instanceof Host) {
                        continue;
                    }

                    $event->addHost($host);
                }
            }

            $image = null;
            $oldPreviewId = $event->getPreviewImage()?->getId();
            $imageData = $form->get('image')->getData();
            if ($imageData instanceof UploadedFile) {
                $image = $this->imageService->upload($imageData, $user, ImageType::EventTeaser);
            }
            if ($image instanceof Image) {
                $event->setPreviewImage($image);
            }

            foreach ($this->languageService->getAdminFilteredEnabledCodes() as $languageCode) {
                $translation = $this->getTranslation($languageCode, $event->getId());
                $translation->setEvent($event);
                $translation->setLanguage($languageCode);
                $translation->setTitle($form->get("title-{$languageCode}")->getData() ?? '');
                $translation->setTeaser($form->get("teaser-{$languageCode}")->getData() ?? '');
                $description = $form->get("description-{$languageCode}")->getData() ?? '';
                $translation->setDescription($this->cmsContent->sanitize($description));

                $this->entityManager->persist($translation);
            }

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            $this->activityService->log(AdminEventEdited::TYPE, $user, ['event_id' => $event->getId()]);
            $this->entityActionDispatcher->dispatch(EntityAction::UpdateEvent, $event->getId());

            if ($form->get('notifyAttendees')->getData() === true) {
                $afterSnapshot = $this->captureEventSnapshot($event);
                $this->dispatchEventUpdateNotifications($event, $user, $beforeSnapshot, $afterSnapshot);
            }

            if ($image instanceof Image) {
                $this->imageService->createThumbnails($image, ImageType::EventTeaser);
                if ($oldPreviewId !== null) {
                    $this->imageLocationService->removeLocation($oldPreviewId, ImageType::EventTeaser, $event->getId());
                }
                $this->imageLocationService->addLocation($image->getId(), ImageType::EventTeaser, $event->getId());
            }

            $syncCount = 0;
            if ($form->get('allFollowing')->getData() === true) {
                $syncCount = $this->eventService->updateRecurringEvents($event, $oldStart);
            }

            // A confirmed rule change realigns without allFollowing; closing the series never realigns
            $executesRealign = $wantsRealign || $isSeries && $ruleChanged && $newRule !== null;
            if ($executesRealign) {
                $result = $this->eventService->executeRealignment($this->eventService->planRealignment($event, $change));
                foreach ($result->removedAttendees as $userId => $removed) {
                    if ($userId === $user->getId()) {
                        continue;
                    }
                    $this->seriesRescheduledEmail->send([
                        'user' => $removed['user'],
                        'event' => $event,
                        'removedDates' => $removed['dates'],
                    ]);
                }
                $this->addFlash('success', $this->translator->trans('admin_event.flash_saved_with_reschedule', [
                    '%updated%' => $syncCount,
                    '%moved%' => $result->movedCount,
                ]));
            } elseif ($form->get('allFollowing')->getData() === true) {
                $this->addFlash('success', $this->translator->trans('admin_event.flash_saved_with_followup', [
                    '%count%' => $syncCount,
                ]));
            } else {
                $this->addFlash('success', $this->translator->trans('admin_event.flash_saved'));
            }

            $this->canonicalRebuildService->refreshAfterEdit($event, $form->get('allFollowing')->getData() === true);

            return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
        }

        if ($form->isSubmitted() && $request->request->has('reschedule_cancel')) {
            $this->entityManager->refresh($event);
        }

        return $this->renderEditPage($event, $form);
    }

    private function renderEditPage(Event $event, FormInterface $form): Response
    {
        return $this->render('admin/event/edit.html.twig', [
            'active' => 'event',
            'event' => $event,
            'form' => $form,
            'adminTop' => new AdminTop(actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('global.button_view'),
                    target: $this->generateUrl('app_event_details', ['id' => $event->getId()]),
                    icon: 'eye',
                    newTab: true,
                ),
                new AdminTopActionButton(
                    label: $this->translator->trans('global.button_back'),
                    target: $this->generateUrl('app_admin_event'),
                    icon: 'arrow-left',
                ),
            ]),
            'notifiableAttendeeCount' => $this->countNotifiableAttendees($event),
            'recurrence' => $this->buildRecurrenceContext($event),
        ]);
    }

    /**
     * @return array{
     *     selection: array{mode: string, period: string, ordinal: list<int>, weekday: list<string>, day: list<int>},
     *     controls: array{ordinal: bool, weekday: bool, weekdayMultiple: bool, day: bool, multiHint: bool, shortMonthHint: bool, periods: list<string>},
     *     summary: string,
     *     currentRule: ?EventInterval,
     *     currentRuleSpec: ?string,
     *     anchor: DateTime|DateTimeImmutable,
     *     customValue: int,
     *     ordinals: list<array{value: int, label: string}>,
     *     weekdays: list<array{value: string, label: string}>,
     *     periods: list<array{value: string, label: string}>
     * }
     */
    private function buildRecurrenceContext(?Event $event): array
    {
        $series = $event?->getSeries();
        $anchor = $event?->getStart() ?? new DateTimeImmutable();
        $currentPattern = $series !== null
            ? $this->recurrenceResolver->resolve($series->getRule(), $series->getRuleSpec(), $anchor)
            : null;
        $isCustom = $series?->getRule() === EventInterval::Custom;

        $state = $this->recurrenceBuilderStateResolver->resolve(
            mode: RecurrenceMode::Weekday,
            period: RecurrencePeriod::Month,
            ordinals: [],
            weekdays: [],
            daysOfMonth: [],
            fallbackWeekday: Weekday::fromDate($anchor),
        );

        return $this->recurrenceStatePayload($state) + [
            'summary' => $isCustom && $currentPattern !== null ? $this->recurrenceDescriber->describe($currentPattern) : '',
            'currentRule' => $series?->getRule(),
            'currentRuleSpec' => $series?->getRuleSpec(),
            'anchor' => $anchor,
            'customValue' => EventInterval::Custom->value,
            'ordinals' => array_map(
                fn(RecurrenceOrdinal $case): array => [
                    'value' => $case->value,
                    'label' => $this->translator->trans($case->label()),
                ],
                RecurrenceOrdinal::cases(),
            ),
            'weekdays' => array_map(
                fn(Weekday $case): array => [
                    'value' => $case->value,
                    'label' => $this->recurrenceDescriber->weekdayName($case),
                ],
                Weekday::cases(),
            ),
            'periods' => array_map(
                fn(RecurrencePeriod $case): array => [
                    'value' => $case->value,
                    'label' => $this->translator->trans($case->label()),
                ],
                array_values(array_filter(RecurrencePeriod::cases(), static fn(RecurrencePeriod $case): bool => $case->carriesDayRule())),
            ),
        ];
    }

    /**
     * @return array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool}
     */
    private function captureEventSnapshot(Event $event): array
    {
        return [
            'start' => $event->getStart()->getTimestamp(),
            'startFormatted' => $event->getStart()->format('Y-m-d H:i'),
            'locationId' => $event->getLocation()?->getId(),
            'locationName' => $event->getLocation()?->getName() ?? '',
            'canceled' => $event->isCanceled(),
        ];
    }

    /**
     * @param array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool} $before
     * @param array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool} $after
     */
    private function dispatchEventUpdateNotifications(Event $event, User $editor, array $before, array $after): void
    {
        if ($before === $after) {
            return;
        }
        if ($event->getStart() <= new DateTime()) {
            return;
        }

        foreach ($event->getRsvp() as $recipient) {
            if (!$recipient instanceof User) {
                continue;
            }
            if ($recipient->getId() === $editor->getId()) {
                continue;
            }
            $this->eventUpdateNotificationEmail->send([
                'user' => $recipient,
                'event' => $event,
                'before' => $before,
                'after' => $after,
            ]);
        }
    }

    private function countNotifiableAttendees(Event $event): int
    {
        if ($event->getStart() <= new DateTime()) {
            return 0;
        }

        $creatorId = $event->getUser()?->getId();
        $count = 0;
        foreach ($event->getRsvp() as $recipient) {
            if (!$recipient instanceof User) {
                continue;
            }
            if ($recipient->getId() === $creatorId) {
                continue;
            }
            if (!$recipient->getNotificationSettings()->isActive('attendedEventUpdate')) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    #[Route('/{id}/delete', name: 'app_admin_event_delete', methods: ['POST'])]
    public function delete(Event $event): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::EVENT_DELETE, $event);

        $this->addFlash('error', $this->translator->trans('admin_event.flash_delete_not_implemented'));

        return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
    }

    #[Route('/{id}/cancel', name: 'app_admin_event_cancel', methods: ['POST'])]
    public function cancel(Event $event): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::EVENT_CANCEL, $event);

        $user = $this->getAuthedUser();
        $rsvpCount = $event->getRsvp()->count();
        $this->eventService->cancelEvent($event);
        $this->activityService->log(AdminEventCancelled::TYPE, $user, ['event_id' => $event->getId()]);
        if ($rsvpCount > 0) {
            $this->addFlash('success', $this->translator->trans('admin_event.flash_canceled', [
                '%count%' => $rsvpCount,
            ]));
        }

        return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
    }

    #[Route('/{id}/uncancel', name: 'app_admin_event_uncancel', methods: ['POST'])]
    public function uncancel(Event $event): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::EVENT_CANCEL, $event);

        $this->eventService->uncancelEvent($event);

        return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
    }

    private function getTranslation(mixed $languageCode, ?int $getId): EventTranslation
    {
        $translation = $this->eventTransRepo->findOneBy(['language' => $languageCode, 'event' => $getId]);
        if ($translation !== null) {
            return $translation;
        }

        return new EventTranslation();
    }

    #[Route('/new', name: 'app_admin_event_add', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::EVENT_CREATE);

        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->remove('createdAt');
        $form->remove('image');
        $form->remove('user');
        $form->remove('status');
        $form->remove('allFollowing');
        $form->remove('notifyAttendees');
        $form->remove('featured');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->validateSeriesName($form)) {
            $user = $this->getAuthedUser();

            $event->setCreatedAt(new DateTimeImmutable());
            $event->setPreviewImage(null);
            $event->setInitial(true);
            $event->setFeatured(false);
            $event->setUser($user);

            $seriesRule = $form->get('seriesRule')->getData();
            if ($seriesRule instanceof EventInterval) {
                $event->setSeries($this->createSeries(
                    trim((string) $form->get('seriesName')->getData()),
                    $seriesRule,
                    $this->resolveSubmittedRuleSpec($form, $seriesRule),
                ));
            }

            $locationData = $form->get('location')->getData();
            if ($locationData instanceof Location) {
                $event->setLocation($locationData);
            }

            $hostsData = $form->get('host')->getData();
            if (is_iterable($hostsData)) {
                foreach ($hostsData as $host) {
                    if (!$host instanceof Host) {
                        continue;
                    }

                    $event->addHost($host);
                }
            }

            $entityManager->persist($event);
            $entityManager->flush();

            $this->activityService->log(AdminEventCreated::TYPE, $user, ['event_id' => $event->getId()]);
            $this->entityActionDispatcher->dispatch(EntityAction::CreateEvent, $event->getId());

            return $this->redirectToRoute('app_admin_event_edit', ['id' => $event->getId()]);
        }

        return $this->render('admin/event/new.html.twig', [
            'active' => 'event',
            'location' => $event,
            'form' => $form,
            'adminTop' => $this->buildBackOnlyTop(),
            'recurrence' => $this->buildRecurrenceContext(null),
        ]);
    }

    private function buildBackOnlyTop(): AdminTop
    {
        return new AdminTop(actions: [
            new AdminTopActionButton(label: $this->translator->trans('global.button_back'), target: $this->generateUrl('app_admin_event'), icon: 'arrow-left'),
        ]);
    }

    private function validateSeriesName(FormInterface $form): bool
    {
        if (!$form->get('seriesRule')->getData() instanceof EventInterval) {
            return true;
        }
        if (trim((string) $form->get('seriesName')->getData()) !== '') {
            return true;
        }
        $form->get('seriesName')->addError(new FormError($this->translator->trans('admin_event.validator_series_name_required', [], 'validators')));

        return false;
    }

    private function resolveSubmittedRuleSpec(FormInterface $form, ?EventInterval $rule): ?string
    {
        if (EventInterval::Custom !== $rule) {
            return null;
        }

        $submitted = trim((string) $form->get('customRuleSpec')->getData());

        return '' === $submitted ? null : $submitted;
    }

    private function createSeries(string $name, EventInterval $rule, ?string $ruleSpec = null): EventSeries
    {
        $series = new EventSeries();
        $series->setName($name);
        $series->setRule($rule);
        $series->setRuleSpec($ruleSpec);
        $series->setCreatedAt(new DateTimeImmutable());
        $this->entityManager->persist($series);

        return $series;
    }

    private function getAuthedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException('Should never happen, see: config/packages/security.yaml');
        }

        return $user;
    }
}
