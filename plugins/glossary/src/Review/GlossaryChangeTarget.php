<?php declare(strict_types=1);

namespace Plugin\Glossary\Review;

use App\Item\Tag\TagService;
use Module\Proposal\Contract\Change\TargetProviderInterface;
use Override;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class GlossaryChangeTarget implements TargetProviderInterface
{
    public const string FIELD_PHRASE = 'phrase';
    public const string FIELD_SECONDARY = 'secondary';
    public const string FIELD_TAG = 'tag';
    public const string DEFINITION_PREFIX = 'definition_';

    public function __construct(
        private GlossaryService $service,
        private ConfigService $configService,
        private TagService $tagService,
        private Security $security,
        private RouterInterface $router,
        private RequestStack $requestStack,
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
        return GlossaryTaggableTypeProvider::ITEM_TYPE;
    }

    #[Override]
    public function getTargetLabel(int $targetId): ?string
    {
        return $this->service->get($targetId)?->getPhrase();
    }

    #[Override]
    public function getTargetUrl(int $targetId): ?string
    {
        return $this->router->generate('app_plugin_glossary_show', ['id' => $targetId]);
    }

    #[Override]
    public function getFieldLabel(string $field): string
    {
        $config = $this->configService->getConfig();

        $language = $this->service->definitionLanguageOf($field);
        if ($language !== null) {
            return $this->translator->trans('glossary.label_definition_in', [
                '%label%' => $config->getDefinitionLabel() ?? $this->translator->trans('glossary.label_definition'),
                '%locale%' => strtoupper($language),
            ]);
        }

        return match ($field) {
            self::FIELD_PHRASE => $config->getPrimaryLabel() ?? $this->translator->trans('glossary.label_phrase'),
            self::FIELD_SECONDARY => $config->getSecondaryLabel() ?? $this->translator->trans('glossary.label_secondary'),
            self::FIELD_TAG => $this->translator->trans('glossary.label_tag'),
            default => $field,
        };
    }

    #[Override]
    public function formatValue(string $field, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($field === self::FIELD_TAG) {
            $locale = $this->requestStack->getCurrentRequest()?->getLocale();
            $labels = [];
            foreach ($this->service->decodeTagIds($value) as $tagId) {
                $tag = $this->tagService->getManagedTag(GlossaryTaggableTypeProvider::ITEM_TYPE, $tagId);
                $labels[] = $tag === null ? (string) $tagId : $this->tagService->labelFor($tag, $locale);
            }

            return implode(', ', $labels);
        }

        return $value;
    }

    #[Override]
    public function canPropose(int $userId, int $targetId): bool
    {
        return $this->security->isGranted('ROLE_USER') && $this->service->get($targetId) !== null;
    }

    #[Override]
    public function canReview(int $userId, int $targetId): bool
    {
        return $this->security->isGranted('ROLE_ORGANIZER') && $this->service->get($targetId) !== null;
    }

    #[Override]
    public function validate(int $targetId, string $field, ?string $value): ?string
    {
        $entry = $this->service->get($targetId);
        if ($entry === null) {
            return $this->translator->trans('glossary.validation_entry_missing');
        }

        if ($field === self::FIELD_PHRASE && trim((string) $value) === '') {
            return $this->translator->trans('glossary.validation_phrase_blank');
        }

        $language = $this->service->definitionLanguageOf($field);
        $clearsDefinition = $language !== null && trim((string) $value) === '';
        if ($clearsDefinition && array_diff_key($entry->getDefinitionMap(), [$language => true]) === []) {
            return $this->translator->trans('glossary.validation_definition_last');
        }

        if ($field === self::FIELD_TAG) {
            $unknown = array_any(
                $this->service->decodeTagIds($value),
                fn(int $tagId): bool => $this->tagService->getManagedTag(GlossaryTaggableTypeProvider::ITEM_TYPE, $tagId) === null,
            );
            if ($unknown) {
                return $this->translator->trans('glossary.validation_tag_unknown');
            }
        }

        return null;
    }

    #[Override]
    public function apply(int $targetId, string $field, ?string $value): void
    {
        $this->service->applyChange($targetId, $field, $value);
    }
}
