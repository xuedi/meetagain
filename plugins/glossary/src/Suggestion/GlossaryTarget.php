<?php declare(strict_types=1);

namespace Plugin\Glossary\Suggestion;

use InvalidArgumentException;
use Module\Suggestion\Contract\TargetProviderInterface;
use Override;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Form\GlossaryType;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Review\GlossaryChangeTarget;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class GlossaryTarget implements TargetProviderInterface
{
    public const string TARGET_TYPE = GlossaryTaggableTypeProvider::ITEM_TYPE;

    public function __construct(
        private GlossaryService $service,
        private ConfigService $configService,
        private Security $security,
        private TranslatorInterface $translator,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'glossary';
    }

    #[Override]
    public function getTargetType(): string
    {
        return self::TARGET_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'glossary.entry_label';
    }

    #[Override]
    public function getFormType(): string
    {
        return GlossaryType::class;
    }

    #[Override]
    public function newDraft(): object
    {
        return new Glossary();
    }

    #[Override]
    public function fromPayload(array $payload): object
    {
        $draft = new Glossary();
        $draft->setPhrase($this->text($payload, 'phrase'));
        $draft->setSecondary($this->optionalText($payload, 'secondary'));
        $draft->submitDefinitions($this->payloadDefinitions($payload));

        return $draft;
    }

    #[Override]
    public function toPayload(object $draft): array
    {
        $entry = $this->entry($draft);

        $payload = [
            'phrase' => (string) $entry->getPhrase(),
            'secondary' => $entry->getSecondary(),
        ];
        foreach ($this->draftDefinitions($entry) as $language => $text) {
            $payload[GlossaryChangeTarget::DEFINITION_PREFIX . $language] = $text;
        }

        return $payload;
    }

    #[Override]
    public function describe(array $payload): string
    {
        return $this->translator->trans('glossary.suggestion_description', [
            '%phrase%' => $this->text($payload, 'phrase'),
        ]);
    }

    #[Override]
    public function summaryRows(array $payload): array
    {
        $config = $this->configService->getConfig();

        $rows = [[
            'label' => $config->getPrimaryLabel() ?? $this->translator->trans('glossary.label_phrase'),
            'value' => $this->text($payload, 'phrase'),
        ]];

        if ($config->isSecondaryEnabled()) {
            $rows[] = [
                'label' => $config->getSecondaryLabel() ?? $this->translator->trans('glossary.label_secondary'),
                'value' => $this->text($payload, 'secondary'),
            ];
        }

        $definitionLabel = $config->getDefinitionLabel() ?? $this->translator->trans('glossary.label_definition');
        foreach ($this->payloadDefinitions($payload) as $language => $text) {
            $rows[] = [
                'label' => $this->translator->trans('glossary.label_definition_in', ['%label%' => $definitionLabel, '%locale%' => strtoupper($language)]),
                'value' => $text,
            ];
        }

        return $rows;
    }

    #[Override]
    public function canPropose(int $userId): bool
    {
        return $this->security->isGranted('ROLE_USER');
    }

    #[Override]
    public function canReview(int $userId): bool
    {
        return $this->security->isGranted('ROLE_ORGANIZER');
    }

    #[Override]
    public function validate(object $draft): ?string
    {
        $entry = $this->entry($draft);
        $phrase = trim((string) $entry->getPhrase());
        if ($phrase === '' || $this->draftDefinitions($entry) === []) {
            return $this->translator->trans('glossary.validator_incomplete');
        }

        return $this->service->isDuplicatePhrase($phrase) ? $this->translator->trans('glossary.validator_duplicate') : null;
    }

    #[Override]
    public function create(object $draft, int $proposerId): int
    {
        $entry = $this->entry($draft);
        $this->service->create($entry, $proposerId);

        return (int) $entry->getId();
    }

    private function entry(object $draft): Glossary
    {
        if (!$draft instanceof Glossary) {
            throw new InvalidArgumentException(sprintf('Expected a Glossary draft, got %s', $draft::class));
        }

        return $draft;
    }

    /** @return array<string, string> */
    private function draftDefinitions(Glossary $entry): array
    {
        $definitions = $entry->getSubmittedDefinitions() ?? $entry->getDefinitionMap();

        return array_filter($definitions, static fn(string $text): bool => trim($text) !== '');
    }

    /**
     * @param array<string, scalar|null> $payload
     * @return array<string, string>
     */
    private function payloadDefinitions(array $payload): array
    {
        $definitions = [];
        foreach ($payload as $key => $value) {
            $language = $this->service->definitionLanguageOf($key);
            if ($language !== null && trim((string) $value) !== '') {
                $definitions[$language] = (string) $value;
            }
        }

        return $definitions;
    }

    /** @param array<string, scalar|null> $payload */
    private function text(array $payload, string $field): string
    {
        return (string) ($payload[$field] ?? '');
    }

    /** @param array<string, scalar|null> $payload */
    private function optionalText(array $payload, string $field): ?string
    {
        $value = (string) ($payload[$field] ?? '');

        return $value === '' ? null : $value;
    }
}
