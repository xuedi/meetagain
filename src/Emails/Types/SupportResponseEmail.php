<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\Guard\Rule\OutboundMailerNotBlocklistedRule;
use App\Emails\Guard\Rule\SupportRequestEmailVerifiedRule;
use App\Emails\MockSampleFactory;
use App\Entity\SupportRequest;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class SupportResponseEmail extends EmailAbstract
{
    public function __construct(
        BlocklistInterface $blocklist,
        MockSampleFactory $samples,
        MailerInterface $mailer,
        private ConfigService $config,
        #[Autowire('%kernel.default_locale%')]
        private string $defaultLocale,
    ) {
        parent::__construct($blocklist, $samples, $mailer);
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

    public function compose(array $context): array
    {
        /** @var SupportRequest $request */
        $request = $context['request'];
        $response = (string) $context['response'];

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $request->getEmail());
        $email->locale($request->getLocale() ?? $request->getRequester()?->getLocale() ?? $this->defaultLocale);
        $email->context([
            'name' => $request->getRequesterLabel(),
            'originalMessage' => $request->getMessage(),
            'response' => $response,
            'createdAt' => $request->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        return [$email];
    }
}
