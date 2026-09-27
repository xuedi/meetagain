<?php declare(strict_types=1);

namespace Module\Email\Internal;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Email\Contract\TemplateProviderInterface;
use Module\Email\Contract\TemplatesInterface;
use Module\Email\Internal\Entity\EmailTemplate;
use Module\Email\Internal\Entity\EmailTemplateTranslation;
use Module\Email\Internal\Repository\EmailTemplateRepository;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class EmailTemplateService implements TemplatesInterface
{
    private const string DEFAULT_LANGUAGE = 'en';

    /** @var array<string, list<string>>|null */
    private ?array $htmlVariables = null;

    /** @param iterable<TemplateProviderInterface> $providers */
    public function __construct(
        private readonly EmailTemplateRepository $repo,
        private readonly EntityManagerInterface $em,
        #[AutowireIterator(TemplateProviderInterface::class)]
        private readonly iterable $providers = [],
    ) {}

    public function getTemplate(string $identifier): ?EmailTemplate
    {
        return $this->repo->findByIdentifier($identifier);
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function getTemplateContent(string $identifier, string $language): array
    {
        $template = $this->repo->findByIdentifier($identifier);
        if (!$template instanceof EmailTemplate) {
            throw new RuntimeException(sprintf('Email template "%s" not found in database.', $identifier));
        }

        $translation = $template->findTranslation($language) ?? $template->findTranslation(self::DEFAULT_LANGUAGE);

        if (!$translation instanceof EmailTemplateTranslation) {
            throw new RuntimeException(sprintf('No translation found for email template "%s".', $identifier));
        }

        return [
            'subject' => $translation->getSubject() ?? '',
            'body' => $translation->getBody() ?? '',
        ];
    }

    public function render(string $identifier, string $locale, array $context): array
    {
        $content = $this->getTemplateContent($identifier, $locale);

        return [
            'subject' => $this->renderSubject($content['subject'], $context),
            'body' => $this->renderContent($identifier, $content['body'], $context),
        ];
    }

    public function seedLanguage(string $code): void
    {
        foreach ($this->getDefaultTemplates($code) as $identifier => $data) {
            $template = $this->repo->findByIdentifier($identifier);
            if (!$template instanceof EmailTemplate || $template->findTranslation($code) instanceof EmailTemplateTranslation) {
                continue;
            }

            $translation = new EmailTemplateTranslation();
            $translation->setEmailTemplate($template);
            $translation->setLanguage($code);
            $translation->setSubject($data['subject']);
            $translation->setBody($data['body']);
            $translation->setUpdatedAt(new DateTimeImmutable());
            $this->em->persist($translation);
        }

        $this->em->flush();
    }

    public function renderContent(string $identifier, string $content, array $context): string
    {
        return $this->substitute($content, $context, escape: true, raw: $this->htmlVariablesOf($identifier));
    }

    public function renderSubject(string $subject, array $context): string
    {
        return $this->substitute($subject, $context, escape: false);
    }

    /**
     * @return array<string, array{subject: string, body: string, variables: list<string>, htmlVariables: list<string>}>
     */
    public function getDefaultTemplates(string $language = self::DEFAULT_LANGUAGE): array
    {
        $templates = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getDefinitions($language) as $definition) {
                $templates[$definition->identifier] = [
                    'subject' => $definition->subject,
                    'body' => $definition->body,
                    'variables' => $definition->variables,
                    'htmlVariables' => $definition->htmlVariables,
                ];
            }
        }

        return $templates;
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string> $raw
     */
    private function substitute(string $content, array $context, bool $escape, array $raw = []): string
    {
        foreach ($context as $key => $value) {
            if ($value !== null && !is_scalar($value)) {
                continue;
            }

            $replacement = (string) $value;
            if ($escape && !in_array($key, $raw, true)) {
                $replacement = htmlspecialchars($replacement, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $content = str_replace('{{' . $key . '}}', $replacement, $content);
        }

        return $content;
    }

    /** @return list<string> */
    private function htmlVariablesOf(string $identifier): array
    {
        if ($this->htmlVariables === null) {
            $this->htmlVariables = array_map(static fn(array $template): array => $template['htmlVariables'], $this->getDefaultTemplates());
        }

        return $this->htmlVariables[$identifier] ?? [];
    }
}
