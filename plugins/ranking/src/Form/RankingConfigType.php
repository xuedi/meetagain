<?php declare(strict_types=1);

namespace Plugin\Ranking\Form;

use Plugin\Ranking\Entity\RankingConfig;
use Plugin\Ranking\Enum\Archetype;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RankingConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('archetype', EnumType::class, [
                'class' => Archetype::class,
                'choice_label' => static fn(Archetype $a) => $a->label(),
                'choice_translation_domain' => 'messages',
                'label' => 'ranking_admin_settings.label_archetype',
            ])
            ->add('showBadge', CheckboxType::class, [
                'label' => 'ranking_admin_settings.label_show_badge',
                'required' => false,
            ])
            ->add('showOnMemberList', CheckboxType::class, [
                'label' => 'ranking_admin_settings.label_show_on_member_list',
                'required' => false,
            ])
            ->add('showLeaderboardNav', CheckboxType::class, [
                'label' => 'ranking_admin_settings.label_show_leaderboard',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RankingConfig::class,
            'translation_domain' => 'messages',
        ]);
    }
}
