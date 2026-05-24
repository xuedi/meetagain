<?php declare(strict_types=1);

namespace Plugin\Ranking\Form;

use Plugin\Ranking\Entity\RankDefinition;
use Plugin\Ranking\Enum\Archetype;
use Plugin\Ranking\ValueObject\MemberRankInput;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class MemberRankType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $archetype = $options['archetype'];
        \assert($archetype instanceof Archetype);

        if ($archetype->isNumeric()) {
            $builder->add('numericValue', IntegerType::class, [
                'label' => 'ranking_my_rank.form_value',
                'required' => true,
                'constraints' => [new Assert\NotNull(), new Assert\GreaterThanOrEqual(0)],
            ]);

            return;
        }

        $builder->add('definition', EntityType::class, [
            'class' => RankDefinition::class,
            'choices' => $options['definitions'],
            'choice_label' => static fn(RankDefinition $d) => $d->getLabelKey() !== null ? $d->getLabelKey() : $d->getLabel(),
            'choice_translation_domain' => 'messages',
            'label' => 'ranking_my_rank.form_rank',
            'required' => true,
            'constraints' => [new Assert\NotNull()],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MemberRankInput::class,
            'translation_domain' => 'messages',
            'definitions' => [],
        ]);
        $resolver->setRequired(['archetype']);
        $resolver->setAllowedTypes('archetype', Archetype::class);
        $resolver->setAllowedTypes('definitions', 'array');
    }
}
