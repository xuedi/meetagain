<?php declare(strict_types=1);

namespace App\Item\Tag;

use App\Entity\ItemTag;
use App\Entity\User;
use App\Review\ChangeTargetFamilyInterface;
use App\Review\ChangeTargetProviderInterface;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ChangeTarget implements ChangeTargetProviderInterface, ChangeTargetFamilyInterface
{
    public const string TYPE_PREFIX = 'item_tag_';
    public const int VOCABULARY_TARGET = 0;
    public const string FIELD_REMOVE = 'remove';
    public const string FIELD_PARENT = 'parent';
    public const string LABEL_PREFIX = 'label_';
    public const string CHILD_PREFIX = 'child_';

    public function __construct(
        private TypeRegistry $registry,
        private TagService $tagService,
        private Security $security,
        private RouterInterface $router,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
        private string $itemType = '',
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return '';
    }

    #[Override]
    public function getTargetType(): string
    {
        return self::TYPE_PREFIX . $this->itemType;
    }

    #[Override]
    public function handlesTargetType(string $targetType): bool
    {
        return $this->typeKeyOf($targetType) !== null;
    }

    #[Override]
    public function forTargetType(string $targetType): ChangeTargetProviderInterface
    {
        return new self(
            $this->registry,
            $this->tagService,
            $this->security,
            $this->router,
            $this->requestStack,
            $this->translator,
            (string) $this->typeKeyOf($targetType),
        );
    }

    #[Override]
    public function getTargetLabel(int $targetId): ?string
    {
        $provider = $this->registry->providerForIncludingInactive($this->itemType);
        if ($provider === null) {
            return null;
        }

        return $this->translator->trans('item.tag_target_label', [
            '%type%' => $this->translator->trans($provider->getLabelKey()),
        ]);
    }

    #[Override]
    public function getTargetUrl(int $targetId): ?string
    {
        return $this->router->generate('app_item_tags', ['itemType' => $this->itemType]);
    }

    #[Override]
    public function getFieldLabel(string $field): string
    {
        $locale = strtoupper($this->localeOf($field));

        return match (true) {
            $field === self::FIELD_REMOVE => $this->translator->trans('item.tag_field_remove'),
            $field === self::FIELD_PARENT => $this->translator->trans('item.tag_field_parent'),
            str_starts_with($field, self::LABEL_PREFIX) => $this->translator->trans('item.tag_field_rename', ['%locale%' => $locale]),
            str_starts_with($field, self::CHILD_PREFIX) => $this->translator->trans('item.tag_field_add', ['%locale%' => $locale]),
            default => $field,
        };
    }

    #[Override]
    public function formatValue(string $field, ?string $value): string
    {
        if ($field !== self::FIELD_PARENT || $value === null || $value === '') {
            return $value ?? '';
        }

        $parent = $this->tagService->getManagedTag($this->itemType, (int) $value);

        return $parent === null ? $value : $this->tagService->labelFor($parent, $this->requestStack->getCurrentRequest()?->getLocale());
    }

    #[Override]
    public function canPropose(User $user, int $targetId): bool
    {
        return $this->security->isGranted('ROLE_USER') && $this->registry->has($this->itemType);
    }

    #[Override]
    public function canReview(User $user, int $targetId): bool
    {
        if (!$this->security->isGranted('ROLE_STEWARD') || !$this->registry->has($this->itemType)) {
            return false;
        }

        return $targetId === self::VOCABULARY_TARGET || $this->tag($targetId) !== null;
    }

    #[Override]
    public function validate(int $targetId, string $field, ?string $value): ?string
    {
        $tag = $targetId === self::VOCABULARY_TARGET ? null : $this->tag($targetId);
        if ($targetId !== self::VOCABULARY_TARGET && $tag === null) {
            return $this->translator->trans('item.tag_validation_gone');
        }

        if ($field === self::FIELD_REMOVE) {
            return null;
        }

        if ($field === self::FIELD_PARENT) {
            return $this->validateParent($tag, $value);
        }

        return $this->validateLabel($field, $tag, $value);
    }

    #[Override]
    public function apply(int $targetId, string $field, ?string $value): void
    {
        $tag = $targetId === self::VOCABULARY_TARGET ? null : $this->tag($targetId);
        if ($targetId !== self::VOCABULARY_TARGET && $tag === null) {
            return;
        }

        if ($tag !== null && $field === self::FIELD_REMOVE) {
            $this->tagService->deleteTag($tag);

            return;
        }

        if ($tag !== null && $field === self::FIELD_PARENT) {
            $parent = $value === null || $value === '' ? null : $this->tagService->getManagedTag($this->itemType, (int) $value);
            $this->tagService->moveTag($tag, $parent);

            return;
        }

        if ($tag !== null && str_starts_with($field, self::LABEL_PREFIX)) {
            $this->tagService->renameTag($tag, $this->localeOf($field), (string) $value);

            return;
        }

        if (!str_starts_with($field, self::CHILD_PREFIX)) {
            return;
        }

        $this->tagService->addTag($this->itemType, [$this->localeOf($field) => trim((string) $value)], $tag);
    }

    private function validateParent(?ItemTag $tag, ?string $value): ?string
    {
        if ($tag === null) {
            return $this->translator->trans('item.tag_validation_gone');
        }

        if ($value === null || $value === '') {
            return null;
        }

        $parent = $this->tagService->getManagedTag($this->itemType, (int) $value);
        if ($parent === null) {
            return $this->translator->trans('item.tag_validation_gone');
        }

        return $this->tagService->canParent($tag, $parent) ? null : $this->translator->trans('item.tag_validation_depth');
    }

    private function validateLabel(string $field, ?ItemTag $tag, ?string $value): ?string
    {
        $isChild = str_starts_with($field, self::CHILD_PREFIX);
        if (!$isChild && !str_starts_with($field, self::LABEL_PREFIX)) {
            return $this->translator->trans('item.tag_validation_gone');
        }

        if ($isChild && $tag !== null && $tag->getDepth() >= ItemTag::MAX_DEPTH) {
            return $this->translator->trans('item.tag_validation_depth');
        }

        $label = trim((string) $value);
        if ($label === '') {
            return $this->translator->trans('item.tag_validation_blank');
        }

        $locale = $this->localeOf($field);
        foreach ($this->tagService->getManagedVocabulary($this->itemType) as $existing) {
            $isSelf = !$isChild && $tag !== null && $existing->getId() === $tag->getId();
            if ($isSelf || mb_strtolower($existing->getLabels()[$locale] ?? '') !== mb_strtolower($label)) {
                continue;
            }

            return $this->translator->trans('item.tag_validation_duplicate');
        }

        return null;
    }

    private function tag(int $targetId): ?ItemTag
    {
        return $this->tagService->getManagedTag($this->itemType, $targetId);
    }

    private function localeOf(string $field): string
    {
        $parts = explode('_', $field);

        return $parts[1] ?? '';
    }

    private function typeKeyOf(string $targetType): ?string
    {
        if (!str_starts_with($targetType, self::TYPE_PREFIX)) {
            return null;
        }

        $typeKey = substr($targetType, strlen(self::TYPE_PREFIX));

        return $this->registry->providerForIncludingInactive($typeKey) === null ? null : $typeKey;
    }
}
