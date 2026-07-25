<?php declare(strict_types=1);

namespace App\Form;

use App\Service\Config\ConfigService;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

class EventCanonicalSettingsType extends AbstractType
{
    public function __construct(
        private readonly ConfigService $configService,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('eventCanonicalThreshold', IntegerType::class, [
            'label' => 'admin_seo_canonical.field_threshold',
            'help' => 'admin_seo_canonical.help_threshold',
            'required' => false,
            'data' => $this->configService->getEventCanonicalThreshold(),
            'constraints' => [new Range(min: 1, max: 100, notInRangeMessage: 'admin_seo_canonical.validator_threshold_range')],
            'attr' => ['min' => 1, 'max' => 100],
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
