<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\OutboundMailerNotBlocklistedRule;
use App\Entity\SupportRequest;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class SupportResponseEmail extends EmailAbstract
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        private EmailQueueInterface $queue,
        private ConfigService $config,
    ) {
        parent::__construct($blocklist);
    }

    public function getIdentifier(): string
    {
        return EmailType::SupportResponse->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_support_response';
    }

    public function getDisplayMockData(): array
    {
        return [
            'subject' => 'Re: your support request',
            'context' => [
                'name' => 'John Doe',
                'originalMessage' => 'I need help with my account.',
                'response' => 'Happy to help - here is what you need to do.',
                'createdAt' => '2025-01-01 12:00:00',
            ],
        ];
    }

    public function getGuardRules(): array
    {
        return [
            new OutboundMailerNotBlocklistedRule($this->blocklist, $this->config),
        ];
    }

    public function send(array $context): void
    {
        /** @var SupportRequest $request */
        $request = $context['request'];
        $response = (string) $context['response'];

        if ($this->blocklist->isBlocked($request->getEmail())) {
            return;
        }

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to($request->getEmail());
        $email->locale('en');
        $email->context([
            'name' => $request->getName(),
            'originalMessage' => $request->getMessage(),
            'response' => $response,
            'createdAt' => $request->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        $this->queue->enqueue($this, $email, EmailType::SupportResponse, $context);
    }
}
