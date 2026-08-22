<?php declare(strict_types=1);

namespace App\Service\Email;

use App\Emails\EmailInterface;
use App\Emails\EmailQueueInterface;
use App\Entity\EmailQueue;
use App\Enum\EmailQueueStatus;
use App\Repository\EmailQueueRepository;
use App\Repository\EmailTemplateRepository;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

readonly class PreviewSweepService
{
    public const string DEFAULT_RECIPIENT_DOMAIN = 'preview.invalid';

    private const array ALLOWED_ENVIRONMENTS = ['dev', 'test'];
    private const int SUBJECT_MAX_LENGTH = 255;

    /** @param iterable<EmailInterface> $emailTypes */
    public function __construct(
        #[AutowireIterator(EmailInterface::class)]
        private iterable $emailTypes,
        private EmailQueueInterface $emailQueue,
        private EmailTemplateRepository $templateRepo,
        private EmailQueueRepository $queueRepo,
        private EntityManagerInterface $em,
        private LanguageService $languageService,
        private ConfigService $config,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {}

    /** @return list<string> */
    public function availableIdentifiers(): array
    {
        $identifiers = array_keys($this->typesByIdentifier());
        sort($identifiers);

        return $identifiers;
    }

    /** @return array<string, EmailInterface> */
    public function typesByIdentifier(): array
    {
        $types = [];
        foreach ($this->emailTypes as $emailType) {
            $types[$emailType->getIdentifier()] = $emailType;
        }

        return $types;
    }

    /**
     * @param list<string> $requestedIdentifiers
     * @param list<string> $requestedLocales
     * @param list<string> $recipientTags
     */
    public function sweep(
        array $requestedIdentifiers = [],
        array $requestedLocales = [],
        string $recipientDomain = self::DEFAULT_RECIPIENT_DOMAIN,
        bool $tagSubjects = true,
        ?object $origin = null,
        array $recipientTags = [],
    ): PreviewSweepResult {
        $this->guardEnvironment();

        $typesByIdentifier = $this->typesByIdentifier();
        $identifiers = $this->resolveIdentifiers($requestedIdentifiers, $typesByIdentifier);
        $locales = $this->resolveLocales($requestedLocales);

        $sender = $this->config->getMailerAddress();
        $enqueued = 0;
        $errors = [];

        foreach ($this->reverseReadingOrder($identifiers, $locales) as [$identifier, $locale]) {
            $recipient = sprintf('%s@%s', implode('+', [$identifier, $locale, ...$recipientTags]), $recipientDomain);
            $emailType = $typesByIdentifier[$identifier];

            try {
                $mockContext = $emailType->getDisplayMockData($locale)['context'];

                $email = new TemplatedEmail();
                $email->from($sender);
                $email->to($recipient);
                $email->locale($locale);
                $email->context($mockContext);

                $this->emailQueue->enqueue($emailType, $email, $mockContext, true, $origin);
                ++$enqueued;
            } catch (Throwable $e) {
                $errors[$recipient] = $e->getMessage();
            }
        }

        if ($tagSubjects) {
            $this->tagPendingSubjects($recipientDomain);
        }

        return new PreviewSweepResult(
            enqueued: $enqueued,
            identifiers: $identifiers,
            locales: $locales,
            withoutType: $this->templatesWithoutType($typesByIdentifier),
            errors: $errors,
            tagged: $tagSubjects,
        );
    }

    private function tagPendingSubjects(string $recipientDomain): void
    {
        $suffix = '@' . $recipientDomain;

        foreach ($this->queueRepo->findBy(['status' => EmailQueueStatus::Pending]) as $row) {
            if (!str_ends_with((string) $row->getRecipient(), $suffix)) {
                continue;
            }

            $row->setSubject($this->tagged($row));
            $this->em->persist($row);
        }

        $this->em->flush();
    }

    private function tagged(EmailQueue $row): string
    {
        $layout = $row->getContext()[LayoutRenderer::CONTEXT_KEY] ?? [];
        $siteName = is_array($layout) ? (string) ($layout['siteName'] ?? '') : '';

        $tag = sprintf('[%s][%s][%s] ', (string) $row->getTemplate(), $siteName, (string) $row->getLang());

        return mb_substr($tag . (string) $row->getSubject(), 0, self::SUBJECT_MAX_LENGTH);
    }

    private function guardEnvironment(): void
    {
        if (in_array($this->environment, self::ALLOWED_ENVIRONMENTS, true)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'The email preview sweep refuses to run in "%s"; it is available in %s only.',
            $this->environment,
            implode(' and ', self::ALLOWED_ENVIRONMENTS),
        ));
    }

    /**
     * @param list<string> $requested
     * @param array<string, EmailInterface> $typesByIdentifier
     * @return list<string>
     */
    private function resolveIdentifiers(array $requested, array $typesByIdentifier): array
    {
        $available = array_keys($typesByIdentifier);
        sort($available);

        if ($requested === []) {
            return $available;
        }

        foreach ($requested as $identifier) {
            if (!in_array($identifier, $available, true)) {
                throw new RuntimeException(sprintf(
                    'Unknown email type "%s". Available: %s',
                    $identifier,
                    implode(', ', $available),
                ));
            }
        }

        return array_values(array_intersect($available, $requested));
    }

    /**
     * @param list<string> $requested
     * @return list<string>
     */
    private function resolveLocales(array $requested): array
    {
        $available = $this->languageService->getFilteredEnabledCodes();

        if ($requested === []) {
            return $available;
        }

        foreach ($requested as $code) {
            if (!in_array($code, $available, true)) {
                throw new RuntimeException(sprintf(
                    'Unknown language "%s". Available: %s',
                    $code,
                    implode(', ', $available),
                ));
            }
        }

        return array_values(array_intersect($available, $requested));
    }

    /**
     * @param list<string> $identifiers
     * @param list<string> $locales
     * @return list<array{0: string, 1: string}>
     */
    private function reverseReadingOrder(array $identifiers, array $locales): array
    {
        $pairs = [];
        foreach ($locales as $locale) {
            foreach ($identifiers as $identifier) {
                $pairs[] = [$identifier, $locale];
            }
        }

        return array_reverse($pairs);
    }

    /**
     * @param array<string, EmailInterface> $typesByIdentifier
     * @return list<string>
     */
    private function templatesWithoutType(array $typesByIdentifier): array
    {
        $orphans = [];
        foreach ($this->templateRepo->findAll() as $template) {
            $identifier = (string) $template->getIdentifier();
            if (!array_key_exists($identifier, $typesByIdentifier)) {
                $orphans[] = $identifier;
            }
        }
        sort($orphans);

        return $orphans;
    }
}
