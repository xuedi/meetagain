<?php declare(strict_types=1);

namespace Module\Suggestion\Internal\Emails;

use App\Emails\EmailAbstract;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Emails\MockSampleFactory;
use App\Entity\User;
use App\Service\Config\ConfigService;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

abstract readonly class ResolvedAbstract extends EmailAbstract
{
    public const array VARIABLES = ['username', 'description', 'host', 'lang', 'greeting'];

    public function __construct(
        BlocklistInterface $blocklist,
        MockSampleFactory $samples,
        MailerInterface $mailer,
        private ConfigService $config,
    ) {
        parent::__construct($blocklist, $samples, $mailer);
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
            'subject' => TemplateProvider::SUBJECTS['en'][$this->getIdentifier()],
            'context' => [
                'username' => $sample->recipientName,
                'description' => $sample->eventTitle,
                'host' => $sample->host,
                'lang' => $locale,
            ],
        ];
    }

    public function compose(array $context): array
    {
        /** @var User $user */
        $user = $context['user'];
        $address = (string) $user->getEmail();
        if ($address === '') {
            return [];
        }

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to($address);
        $email->locale($user->getLocale());
        $email->context([
            'username' => (string) $user->getName(),
            'description' => (string) $context['description'],
            'lang' => $user->getLocale(),
        ]);

        return [$email];
    }
}
