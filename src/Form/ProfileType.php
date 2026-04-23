<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Service\Config\LanguageService;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfileType extends AbstractType
{
    public function __construct(
        private readonly LanguageService $languageService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $user */
        $user = $builder->getData();

        $languageList = [];
        foreach ($this->languageService->getEnabledCodes() as $locale) {
            $languageList[$this->translator->trans('language_' . $locale)] = $locale;
        }

        $builder->add('image', FileType::class, [
            'mapped' => false,
            'required' => false,
            'label' => false,
            'attr' => ['class' => 'is-hidden'],
            'constraints' => [
                new File(
                    maxSize: '10M',
                    mimeTypes: ['image/*'],
                    mimeTypesMessage: 'shared.form_image_upload_mime_error_square',
                ),
            ],
        ])->add('name', TextType::class, [
            'label' => 'profile.form_label_username',
            'constraints' => [
                new Length(max: 64, maxMessage: 'security.validator_username_max'),
            ],
        ])->add('public', ChoiceType::class, [
            'data' => $user->isPublic(),
            'mapped' => false,
            'label' => 'profile.form_label_public_profile',
            'choices' => [
                $this->translator->trans('profile.form_choice_yes') => true,
                $this->translator->trans('profile.form_choice_no') => false,
            ],
        ])->add('languages', ChoiceType::class, [
            'data' => $user->getLocale(),
            'mapped' => false,
            'label' => 'profile.form_label_language_after_login',
            'choices' => $languageList,
        ])->add('bio', TextareaType::class, [
            'data' => $user->getBio(),
            'required' => false,
            'mapped' => false,
            'label' => 'profile.form_label_bio',
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
