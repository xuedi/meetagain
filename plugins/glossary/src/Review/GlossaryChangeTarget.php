<?php declare(strict_types=1);

namespace Plugin\Glossary\Review;

use App\Entity\User;
use App\Item\Taxonomy\ItemTaxonomyService;
use App\Review\ChangeTargetProviderInterface;
use Override;
use Plugin\Glossary\Item\GlossaryCategorizableTypeProvider;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class GlossaryChangeTarget implements ChangeTargetProviderInterface
{
    public const string FIELD_PHRASE = 'phrase';
    public const string FIELD_PINYIN = 'pinyin';
    public const string FIELD_EXPLANATION = 'explanation';
    public const string FIELD_CATEGORY = 'category';

    public function __construct(
        private GlossaryService $service,
        private ConfigService $configService,
        private ItemTaxonomyService $taxonomyService,
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
        return GlossaryCategorizableTypeProvider::ITEM_TYPE;
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

        return match ($field) {
            self::FIELD_PHRASE => $config->getPrimaryLabel() ?? $this->translator->trans('glossary.label_phrase'),
            self::FIELD_PINYIN => $config->getSecondaryLabel() ?? $this->translator->trans('glossary.label_pinyin'),
            self::FIELD_EXPLANATION => $config->getDefinitionLabel() ?? $this->translator->trans('glossary.label_explanation'),
            self::FIELD_CATEGORY => $this->translator->trans('glossary.label_category'),
            default => $field,
        };
    }

    #[Override]
    public function formatValue(string $field, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($field === self::FIELD_CATEGORY) {
            $locale = $this->requestStack->getCurrentRequest()?->getLocale();

            return $this->taxonomyService->categoryLabelForId(GlossaryCategorizableTypeProvider::ITEM_TYPE, (int) $value, $locale) ?? $value;
        }

        return $value;
    }

    #[Override]
    public function canPropose(User $user, int $targetId): bool
    {
        if (!$this->security->isGranted('ROLE_USER')) {
            return false;
        }

        $item = $this->service->get($targetId);

        return $item !== null && $item->getApproved();
    }

    #[Override]
    public function canReview(User $user, int $targetId): bool
    {
        return $this->security->isGranted('ROLE_ORGANIZER') && $this->service->get($targetId) !== null;
    }

    #[Override]
    public function validate(int $targetId, string $field, ?string $value): ?string
    {
        if ($this->service->get($targetId) === null) {
            return $this->translator->trans('glossary.validation_entry_missing');
        }

        if ($field === self::FIELD_PHRASE && trim((string) $value) === '') {
            return $this->translator->trans('glossary.validation_phrase_blank');
        }

        if ($field === self::FIELD_CATEGORY && $value !== null && $value !== ''
            && !$this->configService->getConfig()->getTaxonomy()->hasCategory((int) $value)) {
            return $this->translator->trans('glossary.validation_category_unknown');
        }

        return null;
    }

    #[Override]
    public function apply(int $targetId, string $field, ?string $value): void
    {
        $this->service->applyChange($targetId, $field, $value);
    }
}
