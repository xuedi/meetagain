<?php declare(strict_types=1);

namespace App\Service\Admin;

use App\Emails\Types\AdminNotificationEmail;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Config\ConfigService;
use App\Service\Notification\Admin\AdminNotificationProviderInterface;
use App\Service\Notification\Admin\AdminNotificationSection;
use DateTimeImmutable;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

readonly class AdminNotificationService implements TaskInterface
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

    public function getCadence(): Cadence
    {
        return new Cadence('0 7-21 * * *');
    }

    public function run(): Result
    {
        try {
            $result = $this->processNotification();
            $this->logger->info('Admin notifications processed', ['result' => $result]);

            return new Result(Status::ok, $result);
        } catch (Throwable $e) {
            return new Result(Status::exception, $e->getMessage());
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

            $sent += $this->adminNotificationEmail->send([
                'user' => $recipient,
                'sectionsHtml' => $this->renderSectionsHtml($sections, $recipient->getLocale()),
            ]);
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
