<?php declare(strict_types=1);

namespace App\Form;

use App\Service\Config\ConfigService;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class SeoSettingsType extends AbstractType
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('seoDescriptionDefault', TextareaType::class, [
            'label' => 'admin_system_seo.field_default',
            'required' => false,
            'data' => $this->configService->getSeoDescription('default'),
            'attr' => ['rows' => 3, 'maxlength' => 160, 'placeholder' => $this->translator->trans('admin_system_seo.placeholder_default')],
        ])->add('seoDescriptionEvents', TextareaType::class, [
            'label' => 'admin_system_seo.field_events',
            'required' => false,
            'data' => $this->configService->getSeoDescription('events'),
            'attr' => [
                'rows' => 3,
                'maxlength' => 160,
                'placeholder' => $this->translator->trans('admin_system_seo.placeholder_events'),
            ],
        ])->add('seoDescriptionMembers', TextareaType::class, [
            'label' => 'admin_system_seo.field_members',
            'required' => false,
            'data' => $this->configService->getSeoDescription('members'),
            'attr' => [
                'rows' => 3,
                'maxlength' => 160,
                'placeholder' => $this->translator->trans('admin_system_seo.placeholder_members'),
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
