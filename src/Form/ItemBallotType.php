<?php declare(strict_types=1);

namespace App\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ItemBallotType extends AbstractType
{
    public const string FIELD_ITEMS = 'items';
    public const string FIELD_TERMS = 'ballotTerms';

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($options['candidateItemIds'] as $itemId) {
            $choices[(string) $itemId] = (string) $itemId;
        }

        $builder->add(self::FIELD_ITEMS, ChoiceType::class, [
            'label' => false,
            'choices' => $choices,
            'choice_label' => false,
            'expanded' => true,
            'multiple' => true,
            'data' => array_values($choices),
        ])->add(self::FIELD_TERMS, BallotTermsType::class);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['candidateItemIds' => []]);
        $resolver->setAllowedTypes('candidateItemIds', 'array');
    }
}
