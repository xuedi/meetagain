<?php declare(strict_types=1);

namespace App\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

class SecurityMeasuresType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('powDifficulty', IntegerType::class, [
            'label' => 'admin_system_security.label_pow_difficulty',
            'help' => 'admin_system_security.help_pow_difficulty',
            'constraints' => [
                new Range(notInRangeMessage: 'admin_system_security.validator_pow_difficulty', min: 8, max: 24),
            ],
        ])->add('logRetentionDays', IntegerType::class, [
            'label' => 'admin_system_security.label_log_retention',
            'help' => 'admin_system_security.help_log_retention',
            'constraints' => [
                new Range(notInRangeMessage: 'admin_system_security.validator_log_retention', min: 1, max: 365),
            ],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
