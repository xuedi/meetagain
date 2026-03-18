<?php declare(strict_types=1);

namespace App\Service\Admin;

use App\CronTaskInterface;
use App\Service\Notification\Admin\AdminNotificationProviderInterface;
use App\Service\Notification\Admin\AdminNotificationSection;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\Config\ConfigService;
use DateTimeImmutable;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class AdminNotificationService implements CronTaskInterface
{
    private const string CACHE_KEY = 'admin_notification_last_sent_at';
    private const int CACHE_TTL = 365 * 24 * 3600;

    /**
     * @param iterable<AdminNotificationProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(AdminNotificationProviderInterface::class)]
        private iterable $providers,
        private EmailService $emailService,
        private UserRepository $userRepository,
        private TagAwareCacheInterface $appCache,
        private ConfigService $configService,
        private LoggerInterface $logger,
        private ClockInterface $clock,
    ) {}

    public function runCronTask(OutputInterface $output): void
    {
        $currentHour = (int) $this->clock->now()->format('H');
        if ($currentHour < 7 || $currentHour >= 22) {
            $output->writeln('Admin notifications skipped: outside allowed hours (07:00-22:00)');

            return;
        }

        $result = $this->processNotification();
        $output->writeln('Admin notifications: ' . $result);
        $this->logger->info('Admin notifications processed', ['result' => $result]);
    }

    public function processNotification(): string
    {
        if (!$this->configService->isSendAdminNotification()) {
            return 'disabled';
        }

        $latestPendingAt = $this->getLatestPendingAt();
        if ($latestPendingAt === null) {
            return 'nothing pending';
        }

        $lastSentAt = $this->getLastSentAt();
        if ($lastSentAt !== null && $latestPendingAt <= $lastSentAt) {
            return 'no new items';
        }

        $sections = $this->collectSections();
        if ($sections === []) {
            return 'no items';
        }

        $sectionsHtml = $this->renderSectionsHtml($sections);
        $recipients = $this->userRepository->findAdminUsers();

        foreach ($recipients as $recipient) {
            $this->emailService->prepareAdminNotification($recipient, $sectionsHtml);
        }

        $this->updateLastSentAt();

        return sprintf('%d sent', count($recipients));
    }

    private function getLatestPendingAt(): ?DateTimeImmutable
    {
        $latest = null;
        foreach ($this->providers as $provider) {
            $providerLatest = $provider->getLatestPendingAt();
            if ($providerLatest !== null && ($latest === null || $providerLatest > $latest)) {
                $latest = $providerLatest;
            }
        }

        return $latest;
    }

    /**
     * @return AdminNotificationSection[]
     */
    private function collectSections(): array
    {
        $sections = [];
        foreach ($this->providers as $provider) {
            $items = $provider->getPendingItems();
            if ($items !== []) {
                $sections[] = new AdminNotificationSection($provider->getSection(), $items);
            }
        }

        return $sections;
    }

    /**
     * @param AdminNotificationSection[] $sections
     */
    private function renderSectionsHtml(array $sections): string
    {
        $html = '';
        foreach ($sections as $section) {
            $html .= sprintf('<h3>%s</h3><ul>', htmlspecialchars($section->title));
            foreach ($section->items as $item) {
                $html .= sprintf('<li>%s</li>', htmlspecialchars($item->label));
            }
            $html .= '</ul>';
        }

        return $html;
    }

    private function getLastSentAt(): ?DateTimeImmutable
    {
        try {
            $value = $this->appCache->get(self::CACHE_KEY, fn(ItemInterface $item) => null);

            return $value !== null ? new DateTimeImmutable($value) : null;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function updateLastSentAt(): void
    {
        try {
            $now = $this->clock->now()->format(DateTimeImmutable::ATOM);
            $this->appCache->get(
                self::CACHE_KEY,
                function (ItemInterface $item) use ($now) {
                    $item->expiresAfter(self::CACHE_TTL);

                    return $now;
                },
                beta: INF,
            );
        } catch (InvalidArgumentException) {
            // Non-critical — worst case: one extra notification sent on next run
        }
    }
}
