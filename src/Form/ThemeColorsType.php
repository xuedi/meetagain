<?php declare(strict_types=1);

namespace App\Form;

use App\Service\Config\ConfigService;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ThemeColorsType extends AbstractType
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $colors = $this->configService->getThemeColors();
        $t = $this->translator;

        $builder
            ->add('color_primary', ColorType::class, [
                'label' => 'admin_system_theme.field_color_primary',
                'data' => $colors['color_primary'],
                'attr' => ['title' => $t->trans('admin_system_theme.color_primary')],
            ])
            ->add('color_link', ColorType::class, [
                'label' => 'admin_system_theme.field_color_link',
                'data' => $colors['color_link'],
                'attr' => ['title' => $t->trans('admin_system_theme.color_link')],
            ])
            ->add('color_info', ColorType::class, [
                'label' => 'admin_system_theme.field_color_info',
                'data' => $colors['color_info'],
                'attr' => ['title' => $t->trans('admin_system_theme.color_info')],
            ])
            ->add('color_success', ColorType::class, [
                'label' => 'admin_system_theme.field_color_success',
                'data' => $colors['color_success'],
                'attr' => ['title' => $t->trans('admin_system_theme.color_success')],
            ])
            ->add('color_warning', ColorType::class, [
                'label' => 'admin_system_theme.field_color_warning',
                'data' => $colors['color_warning'],
                'attr' => ['title' => $t->trans('admin_system_theme.color_warning')],
            ])
            ->add('color_danger', ColorType::class, [
                'label' => 'admin_system_theme.field_color_danger',
                'data' => $colors['color_danger'],
                'attr' => ['title' => $t->trans('admin_system_theme.color_danger')],
            ])
            ->add('color_text_grey', ColorType::class, [
                'label' => 'admin_system_theme.field_color_text_grey',
                'data' => $colors['color_text_grey'],
                'attr' => ['title' => $t->trans('admin_system_theme.color_text_grey')],
            ])
            ->add('color_text_grey_light', ColorType::class, [
                'label' => 'admin_system_theme.field_color_text_grey_light',
                'data' => $colors['color_text_grey_light'],
                'attr' => ['title' => $t->trans('admin_system_theme.color_text_grey_light')],
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
