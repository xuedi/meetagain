<?php declare(strict_types=1);

namespace App\Form;

use App\Service\ConfigService;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SeoSettingsType extends AbstractType
{
    public function __construct(
        private readonly ConfigService $configService,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('seoDescriptionDefault', TextareaType::class, [
                'label' => 'SEO: Default description',
                'required' => false,
                'data' => $this->configService->getSeoDescription('default'),
                'attr' => ['rows' => 3, 'maxlength' => 160, 'placeholder' => 'Site-wide meta description (max 160 chars)'],
            ])
            ->add('seoDescriptionEvents', TextareaType::class, [
                'label' => 'SEO: Events page description',
                'required' => false,
                'data' => $this->configService->getSeoDescription('events'),
                'attr' => ['rows' => 3, 'maxlength' => 160, 'placeholder' => 'Meta description for the events listing (max 160 chars)'],
            ])
            ->add('seoDescriptionMembers', TextareaType::class, [
                'label' => 'SEO: Members page description',
                'required' => false,
                'data' => $this->configService->getSeoDescription('members'),
                'attr' => ['rows' => 3, 'maxlength' => 160, 'placeholder' => 'Meta description for the members listing (max 160 chars)'],
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
