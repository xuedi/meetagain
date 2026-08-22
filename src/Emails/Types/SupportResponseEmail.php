<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\OutboundMailerNotBlocklistedRule;
use App\Emails\Guard\Rule\SupportRequestEmailVerifiedRule;
use App\Emails\MockSampleFactory;
use App\Entity\SupportRequest;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class SupportResponseEmail extends EmailAbstract
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
        return EmailType::SupportResponse->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_support_response';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => 'Re: your support request',
            'context' => [
                'name' => $sample->recipientName,
                'originalMessage' => $sample->messageText,
                'response' => $sample->responseText,
                'createdAt' => $sample->dates->createdAt,
                'lang' => $locale,
            ],
        ];
    }

    public function getGuardRules(): array
    {
        return [
            new SupportRequestEmailVerifiedRule(),
            new OutboundMailerNotBlocklistedRule($this->blocklist, $this->config),
        ];
    }

    public function send(array $context): void
    {
        /** @var SupportRequest $request */
        $request = $context['request'];
        $response = (string) $context['response'];

        if (!$request->isEmailVerified() || $this->blocklist->isBlocked((string) $request->getEmail())) {
            return;
        }

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $request->getEmail());
        $email->locale('en');
        $email->context([
            'name' => $request->getRequesterLabel(),
            'originalMessage' => $request->getMessage(),
            'response' => $response,
            'createdAt' => $request->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        $this->queue->enqueue($this, $email, $context);
    }
}
