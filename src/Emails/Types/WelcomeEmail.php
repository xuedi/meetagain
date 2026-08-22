<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Emails\MockSampleFactory;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class WelcomeEmail extends EmailAbstract
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
        return EmailType::Welcome->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_welcome';
    }

    public function getGuardRules(): array
    {
        return [
            new RecipientUserPresentRule(),
            new RecipientNotBlocklistedRule($this->blocklist),
        ];
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => 'Welcome!',
            'context' => [
                'host' => $sample->host,
                'url' => $sample->url,
                'lang' => $locale,
            ],
        ];
    }

    public function getOrigin(array $context): ?object
    {
        $user = $context['user'] ?? null;

        return $user instanceof User ? $user : null;
    }

    public function send(array $context): void
    {
        /** @var User $user */
        $user = $context['user'];

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($user->getLocale());
        $email->context([
            'lang' => $user->getLocale(),
        ]);

        $this->queue->enqueue($this, $email, $context);
    }

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return $now->add(new DateInterval('PT12H'));
    }
}
