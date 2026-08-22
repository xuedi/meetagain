<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\EventInContextRule;
use App\Emails\Guard\Rule\NotificationToggleEnabledRule;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Emails\Guard\Rule\UserNotificationsMasterToggleRule;
use App\Emails\MockSampleFactory;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class SeriesRescheduledEmail extends EmailAbstract
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        MockSampleFactory $samples,
        private EmailQueueInterface $queue,
        private ConfigService $config,
    ) {
        parent::__construct($blocklist, $samples);
    }

    public function getIdentifier(): string
    {
        return EmailType::SeriesRescheduled->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_series_rescheduled';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => sprintf('Series rescheduled: %s', $sample->eventTitle),
            'context' => [
                'username' => $sample->recipientName,
                'eventId' => $sample->eventId,
                'eventTitle' => $sample->eventTitle,
                'host' => $sample->host,
                'lang' => $locale,
                'removedDatesHtml' => $this->samples->removedDatesHtml($sample),
                'newStart' => $sample->eventStart,
            ],
        ];
    }

    public function getGuardRules(): array
    {
        return [
            new RecipientUserPresentRule(),
            new EventInContextRule(),
            new UserNotificationsMasterToggleRule(),
            new NotificationToggleEnabledRule('attendedEventUpdate'),
            new RecipientNotBlocklistedRule($this->blocklist),
        ];
    }

    public function getOrigin(array $context): ?object
    {
        $event = $context['event'] ?? null;

        return $event instanceof Event ? $event : null;
    }

    public function send(array $context): void
    {
        /** @var User $user */
        $user = $context['user'];
        /** @var Event $event */
        $event = $context['event'];
        /** @var list<DateTimeImmutable> $removedDates */
        $removedDates = $context['removedDates'];

        $language = $user->getLocale();
        $formattedDates = array_map(static fn(DateTimeImmutable $date): string => $date->format('Y-m-d H:i'), $removedDates);

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($language);
        $email->context([
            'username' => $user->getName(),
            'eventId' => $event->getId(),
            'eventTitle' => $event->getTitle($language),
            'lang' => $language,
            'removedDatesHtml' => '<ul><li>' . implode('</li><li>', $formattedDates) . '</li></ul>',
            'newStart' => $event->getStart()->format('Y-m-d H:i'),
        ]);

        $this->queue->enqueue($this, $email, $context);
    }

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return $now->add(new DateInterval('PT6H'));
    }
}
