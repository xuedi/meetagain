<?php declare(strict_types=1);

namespace App\Form;

use App\Service\ConfigService;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ThemeColorsType extends AbstractType
{
    public function __construct(private readonly ConfigService $configService)
    {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $colors = $this->configService->getThemeColors();

        $builder
            ->add('color_primary', ColorType::class, [
                'label' => 'Primary',
                'data' => $colors['color_primary'],
                'attr' => ['title' => 'Buttons, highlights'],
            ])
            ->add('color_link', ColorType::class, [
                'label' => 'Link',
                'data' => $colors['color_link'],
                'attr' => ['title' => 'Links, interactive elements'],
            ])
            ->add('color_info', ColorType::class, [
                'label' => 'Info',
                'data' => $colors['color_info'],
                'attr' => ['title' => 'Information messages'],
            ])
            ->add('color_success', ColorType::class, [
                'label' => 'Success',
                'data' => $colors['color_success'],
                'attr' => ['title' => 'Success messages'],
            ])
            ->add('color_warning', ColorType::class, [
                'label' => 'Warning',
                'data' => $colors['color_warning'],
                'attr' => ['title' => 'Warning messages'],
            ])
            ->add('color_danger', ColorType::class, [
                'label' => 'Danger',
                'data' => $colors['color_danger'],
                'attr' => ['title' => 'Error messages, delete buttons'],
            ])
            ->add('color_text_grey', ColorType::class, [
                'label' => 'Text Grey',
                'data' => $colors['color_text_grey'],
                'attr' => ['title' => 'Secondary text (accessibility)'],
            ])
            ->add('color_text_grey_light', ColorType::class, [
                'label' => 'Text Grey Light',
                'data' => $colors['color_text_grey_light'],
                'attr' => ['title' => 'Subtle text (accessibility)'],
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
