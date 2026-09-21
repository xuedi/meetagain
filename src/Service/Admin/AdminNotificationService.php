<?php declare(strict_types=1);

namespace App\Service\Admin;

use App\CronTaskInterface;
use App\Emails\Types\AdminNotificationEmail;
use App\Entity\User;
use App\Enum\CronTaskStatus;
use App\Repository\UserRepository;
use App\Service\Config\ConfigService;
use App\Service\Notification\Admin\AdminNotificationProviderInterface;
use App\Service\Notification\Admin\AdminNotificationSection;
use App\ValueObject\CronTaskResult;
use DateTimeImmutable;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

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
        private AdminNotificationEmail $adminNotificationEmail,
        private UserRepository $userRepository,
        private TagAwareCacheInterface $appCache,
        private ConfigService $configService,
        private LoggerInterface $logger,
        private ClockInterface $clock,
        private TranslatorInterface $translator,
    ) {}

    public function getIdentifier(): string
    {
        return 'admin-notifications';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        try {
            $currentHour = (int) $this->clock->now()->format('H');
            if ($currentHour < 7 || $currentHour >= 22) {
                $message = 'skipped: outside allowed hours (07:00-22:00)';
                $output->writeln('Admin notifications ' . $message);

                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $message);
            }

            $result = $this->processNotification();
            $output->writeln('Admin notifications: ' . $result);
            $this->logger->info('Admin notifications processed', ['result' => $result]);

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $result);
        } catch (Throwable $e) {
            $output->writeln('AdminNotificationService exception: ' . $e->getMessage());

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
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

        $sent = 0;
        foreach ($this->userRepository->findAdminUsers() as $recipient) {
            $sections = $this->collectSections($recipient);
            if ($sections === []) {
                continue;
            }

            $this->adminNotificationEmail->send([
                'user' => $recipient,
                'sectionsHtml' => $this->renderSectionsHtml($sections, $recipient->getLocale()),
            ]);
            ++$sent;
        }

        if ($sent === 0) {
            return 'no items';
        }

        $this->updateLastSentAt();

        return sprintf('%d sent', $sent);
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
    private function collectSections(User $recipient): array
    {
        $sections = [];
        foreach ($this->providers as $provider) {
            $items = $provider->getPendingItems($recipient);
            if ($items !== []) {
                $sections[] = new AdminNotificationSection($provider->getSection(), $items);
            }
        }

        return $sections;
    }

    /**
     * @param AdminNotificationSection[] $sections
     */
    private function renderSectionsHtml(array $sections, string $locale): string
    {
        $html = '';
        foreach ($sections as $section) {
            $html .= sprintf('<h3>%s</h3><ul>', htmlspecialchars($this->translate($section->title, $locale)));
            foreach ($section->items as $item) {
                $html .= sprintf('<li>%s</li>', htmlspecialchars($this->translate($item->label, $locale)));
            }
            $html .= '</ul>';
        }

        return $html;
    }

    private function translate(string|TranslatableInterface $text, string $locale): string
    {
        return $text instanceof TranslatableInterface ? $text->trans($this->translator, $locale) : $text;
    }

    private function getLastSentAt(): ?DateTimeImmutable
    {
        try {
            /** @var string|null $value */
            $value = $this->appCache->get(self::CACHE_KEY, static fn(ItemInterface $item) => null);

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
                static function (ItemInterface $item) use ($now) {
                    $item->expiresAfter(self::CACHE_TTL);

                    return $now;
                },
                beta: INF,
            );
        } catch (InvalidArgumentException $e) {
            $this->logger->debug('Cache write failure for admin notification tracking - non-critical', [
                'exception' => $e,
            ]);
        }
    }
}
