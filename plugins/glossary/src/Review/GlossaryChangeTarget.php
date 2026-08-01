<?php declare(strict_types=1);

namespace Plugin\Glossary\Review;

use App\Entity\User;
use App\Item\Tag\TagService;
use App\Review\ChangeTargetProviderInterface;
use Override;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
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
    public const string FIELD_TAG = 'tag';

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

        return match ($field) {
            self::FIELD_PHRASE => $config->getPrimaryLabel() ?? $this->translator->trans('glossary.label_phrase'),
            self::FIELD_PINYIN => $config->getSecondaryLabel() ?? $this->translator->trans('glossary.label_pinyin'),
            self::FIELD_EXPLANATION => $config->getDefinitionLabel() ?? $this->translator->trans('glossary.label_explanation'),
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
