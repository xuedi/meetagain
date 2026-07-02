<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\EventInContextRule;
use App\Emails\Guard\Rule\NotificationToggleEnabledRule;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Emails\Guard\Rule\UserNotificationsMasterToggleRule;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use App\Service\Http\RequestHostResolver;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class SeriesRescheduledEmail extends EmailAbstract
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        private EmailQueueInterface $queue,
        private ConfigService $config,
        private RequestHostResolver $host,
    ) {
        parent::__construct($blocklist);
    }

    public function getIdentifier(): string
    {
        return EmailType::SeriesRescheduled->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_series_rescheduled';
    }

    public function getDisplayMockData(): array
    {
        return [
            'subject' => 'Series rescheduled: Go tournament afterparty',
            'context' => [
                'username' => 'John Doe',
                'eventId' => 1,
                'eventTitle' => 'Go tournament afterparty',
                'host' => 'https://localhost',
                'lang' => 'en',
                'removedDatesHtml' => '<ul><li>2026-06-15 19:30</li><li>2026-06-22 19:30</li></ul>',
                'newStart' => '2026-06-17 21:00',
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
            'host' => $this->host->getSchemeAndHost(),
            'lang' => $language,
            'removedDatesHtml' => '<ul><li>' . implode('</li><li>', $formattedDates) . '</li></ul>',
            'newStart' => $event->getStart()->format('Y-m-d H:i'),
        ]);

        $this->queue->enqueue($this, $email, EmailType::SeriesRescheduled, $context);
    }

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return $now->add(new DateInterval('PT6H'));
    }
}
