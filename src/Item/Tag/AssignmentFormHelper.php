<?php declare(strict_types=1);

namespace App\Item\Tag;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class AssignmentFormHelper
{
    public const string TAGS_FIELD = 'itemTags';

    public function __construct(
        private TagService $tagService,
        private RequestStack $requestStack,
    ) {}

    public function addAssignmentFields(FormBuilderInterface $builder, string $typeKey, ?int $itemId): void
    {
        $choices = $this->tagService->getAssignableChoices($typeKey, $this->requestStack->getCurrentRequest()?->getLocale());
        if ($choices === []) {
            return;
        }

        $depths = $this->tagService->getDepths($typeKey);
        $parents = array_intersect_key($this->tagService->getParents($typeKey), $choices);
        $nested = array_any($parents, static fn(?int $parent): bool => $parent !== null);

        $builder->add(self::TAGS_FIELD, ChoiceType::class, [
            'label' => 'item.tags_label',
            'choices' => array_flip($choices),
            'choice_attr' => static fn(int $id): array => [
                'data-item-tag-depth' => $depths[$id] ?? 1,
                'data-item-tag-parent' => (string) ($parents[$id] ?? ''),
            ],
            'attr' => $nested ? ['data-item-tag-tree' => ''] : [],
            'block_prefix' => 'item_tags',
            'required' => false,
            'multiple' => true,
            'expanded' => true,
            'mapped' => false,
            'data' => $itemId !== null ? $this->assigned($typeKey, $itemId, $choices) : [],
        ]);
    }

    /** @return list<int> */
    public function extractAssignment(FormInterface $form): array
    {
        if (!$form->has(self::TAGS_FIELD)) {
            return [];
        }

        return array_map('intval', array_values($form->get(self::TAGS_FIELD)->getData() ?? []));
    }

    /**
     * @param  array<int, string> $choices
     * @return list<int>
     */
    private function assigned(string $typeKey, int $itemId, array $choices): array
    {
        $assigned = $this->tagService->getTagIds($typeKey, $itemId);

        return array_values(array_filter($assigned, static fn(int $id): bool => isset($choices[$id])));
    }
}
