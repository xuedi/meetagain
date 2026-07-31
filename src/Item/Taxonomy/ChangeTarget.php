<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Entity\User;
use App\Review\ChangeTargetProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class ChangeTarget implements ChangeTargetProviderInterface
{
    public const string TYPE_PREFIX = 'taxonomy_';

    public function __construct(
        protected readonly CategorizableTypeRegistry $registry,
        protected readonly ScopedSettings $settings,
        protected readonly ScopeCodec $scopeCodec,
        protected readonly ChangeFieldCodec $fieldCodec,
        protected readonly Security $security,
        protected readonly RouterInterface $router,
        protected readonly TranslatorInterface $translator,
    ) {}

    abstract protected function getTypeKey(): string;

    public function getTargetType(): string
    {
        return self::TYPE_PREFIX . $this->getTypeKey();
    }

    public function getTargetLabel(int $targetId): ?string
    {
        $provider = $this->provider();
        if ($provider === null) {
            return null;
        }

        return $this->translator->trans('item.taxonomy_target_label', [
            '%type%' => $this->translator->trans($provider->getLabelKey()),
        ]);
    }

    public function getTargetUrl(int $targetId): ?string
    {
        return $this->router->generate('app_item_taxonomy', ['itemType' => $this->getTypeKey()]);
    }

    public function getFieldLabel(string $field): string
    {
        $parsed = $this->fieldCodec->parse($field);
        if ($parsed === null) {
            return $field;
        }

        return $this->translator->trans($parsed->labelKey(), ['%locale%' => strtoupper($parsed->locale ?? '')]);
    }

    public function formatValue(string $field, ?string $value): string
    {
        return $value ?? '';
    }

    public function canPropose(User $user, int $targetId): bool
    {
        return $this->security->isGranted('ROLE_USER') && $this->isCurrentScope($targetId) && $this->provider() !== null;
    }

    public function canReview(User $user, int $targetId): bool
    {
        if (!$this->isCurrentScope($targetId) || $this->provider() === null) {
            return false;
        }

        return $this->security->isGranted($targetId === ScopeCodec::GLOBAL_TARGET ? 'ROLE_ADMIN' : 'ROLE_STEWARD');
    }

    public function validate(int $targetId, string $field, ?string $value): ?string
    {
        $parsed = $this->fieldCodec->parse($field);
        $taxonomy = $this->taxonomyAt($targetId);
        if ($parsed === null || $taxonomy === null) {
            return $this->translator->trans('item.taxonomy_validation_unavailable');
        }

        if ($parsed->operation === ChangeOperation::Remove) {
            return $taxonomy->hasDefinition($parsed->axis, (int) $parsed->id)
                ? null
                : $this->translator->trans('item.taxonomy_validation_gone');
        }

        if ($parsed->operation === ChangeOperation::Rename && !$taxonomy->hasDefinition($parsed->axis, (int) $parsed->id)) {
            return $this->translator->trans('item.taxonomy_validation_gone');
        }

        if ($parsed->parent !== null && !$taxonomy->hasDefinition($parsed->axis, $parsed->parent)) {
            return $this->translator->trans('item.taxonomy_validation_gone');
        }

        if ($parsed->parent !== null && $taxonomy->tagTree()->depthOf($parsed->parent) >= $taxonomy->getTagDepth()) {
            return $this->translator->trans('item.taxonomy_validation_depth');
        }

        $label = trim((string) $value);
        if ($label === '') {
            return $this->translator->trans('item.taxonomy_validation_blank');
        }

        foreach ($taxonomy->labelsInLocale($parsed->axis, (string) $parsed->locale) as $id => $existing) {
            if ($id === $parsed->id || mb_strtolower($existing) !== mb_strtolower($label)) {
                continue;
            }

            return $this->translator->trans('item.taxonomy_validation_duplicate');
        }

        return null;
    }

    public function apply(int $targetId, string $field, ?string $value): void
    {
        $parsed = $this->fieldCodec->parse($field);
        $provider = $this->provider();
        $data = $provider === null ? null : $this->settings->load($provider->getSettingsKey(), $this->scopeCodec->decode($targetId));
        if ($parsed === null || $provider === null || $data === null) {
            return;
        }

        $taxonomy = $provider->taxonomyOf($data);
        match ($parsed->operation) {
            ChangeOperation::Add => $taxonomy->addLabel($parsed->axis, (string) $parsed->locale, trim((string) $value), $parsed->parent),
            ChangeOperation::Rename => $taxonomy->setLabel($parsed->axis, (int) $parsed->id, (string) $parsed->locale, trim((string) $value)),
            ChangeOperation::Remove => $taxonomy->removeDefinition($parsed->axis, (int) $parsed->id),
        };
        $taxonomy->normalize();
        $this->settings->save($provider->getSettingsKey(), $data, $this->scopeCodec->decode($targetId));
    }

    private function provider(): ?CategorizableTypeProviderInterface
    {
        return $this->registry->providerFor($this->getTypeKey());
    }

    private function isCurrentScope(int $targetId): bool
    {
        return $this->scopeCodec->currentTargetId() === $targetId;
    }

    private function taxonomyAt(int $targetId): ?Config
    {
        $provider = $this->provider();
        if ($provider === null) {
            return null;
        }

        $data = $this->settings->load($provider->getSettingsKey(), $this->scopeCodec->decode($targetId));

        return $data === null ? null : $provider->taxonomyOf($data);
    }
}
