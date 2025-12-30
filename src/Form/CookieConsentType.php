<?php declare(strict_types=1);

namespace App\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CookieConsentType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cookies', CheckboxType::class, [
                'label' => 'cookie_consent_cookies',
                'required' => false,
                'data' => $options['cookies_granted'],
            ])
            ->add('osm', CheckboxType::class, [
                'label' => 'menu_cookie_consent_option_osm',
                'required' => false,
                'data' => $options['osm_granted'],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'save',
                'attr' => ['class' => 'button is-primary'],
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'cookies_granted' => false,
            'osm_granted' => false,
        ]);
    }
}
