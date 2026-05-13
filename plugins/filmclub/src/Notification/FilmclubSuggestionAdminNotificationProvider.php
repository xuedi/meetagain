<?php declare(strict_types=1);

namespace Plugin\Filmclub\Notification;

use App\Service\Notification\Admin\AdminNotificationItem;
use App\Service\Notification\Admin\AdminNotificationProviderInterface;
use DateTimeImmutable;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmSuggestionRepository;
use Plugin\Filmclub\Service\SuggestionService;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class FilmclubSuggestionAdminNotificationProvider implements AdminNotificationProviderInterface
{
    public function __construct(
        private SuggestionService $suggestionService,
        private FilmSuggestionRepository $suggestionRepository,
        private FilmGroupFilterService $groupFilter,
        private TranslatorInterface $translator,
    ) {}

    public function getSection(): string
    {
        return $this->translator->trans('filmclub_notifications.section_pending_suggestions');
    }

    public function getPendingItems(): array
    {
        $suggestions = $this->suggestionService->getPendingSuggestions();
        $items = [];

        foreach ($suggestions as $suggestion) {
            $film = $suggestion->getFilm();
            if ($film === null) {
                continue;
            }

            $items[] = new AdminNotificationItem(
                label: (string) $film->getTitle(),
                route: 'app_plugin_filmclub_film_show',
                routeParams: ['id' => $film->getId()],
            );
        }

        return $items;
    }

    public function getLatestPendingAt(): ?DateTimeImmutable
    {
        return $this->suggestionRepository->getLatestPendingAt(
            $this->groupFilter->getAllowedSuggestionIds(),
        );
    }
}
