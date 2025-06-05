<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
        private readonly ParameterBagInterface $appParams,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $user */
        $user = $builder->getData();

        $languageList = [];
        foreach ($this->appParams->get('kernel.enabled_locales') as $locale) {
            $languageList[$this->translator->trans('language_' . $locale)] = $locale;
        }

        $builder->add('image', FileType::class, [
            'mapped' => false,
            'required' => false,
            'label' => false,
            'constraints' => [
                new File([
                    'maxSize' => '10M',
                    'mimeTypes' => [
                        'image/*',
                    ],
                    'mimeTypesMessage' => 'Please upload a valid image, preferable a square format',
                ])
            ],
        ])->add('name', TextType::class, [
            'label' => 'Username',
            'constraints' => [
                new Length([
                    'maxMessage' => 'usernames cant be longer than 64 characters (less with chinese)',
                    'max' => 64,
                ]),
            ],
        ])->add(
            'public',
            ChoiceType::class,
            [
                'data' => $user->isPublic(),
                'mapped' => false,
                'label' => 'Public profile:',
                'choices' => [$this->translator->trans('Yes') => true, $this->translator->trans('No') => false],
            ]
        )->add(
            'languages',
            ChoiceType::class,
            [
                'data' => $user->getLocale(),
                'mapped' => false,
                'label' => 'Language after login:',
                'choices' => $languageList,
            ]
        )->add(
            'bio',
            TextareaType::class,
            [
                'data' => $user->getBio(),
                'required' => false,
                'mapped' => false,
                'label' => 'Bio:',
            ]
        );
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
