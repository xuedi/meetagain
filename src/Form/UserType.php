<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Entity\UserStatus;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserType extends AbstractType
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

        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'constraints' => [
                    new Length(
                        max: 64,
                        maxMessage: 'usernames cant be longer than 64 characters (less with chinese)',
                    ),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new Length(
                        max: 180,
                        maxMessage: 'emails cant be longer than 180 characters',
                    ),
                ],
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Bio',
            ])
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    $this->translator->trans('role_system') => 'ROLE_SYSTEM',
                    $this->translator->trans('role_admin') => 'ROLE_ADMIN',
                    $this->translator->trans('role_manager') => 'ROLE_MANAGER',
                    $this->translator->trans('role_user') => 'ROLE_USER',
                ],
                'expanded' => true,
                'multiple' => true,
            ])
            ->add('verified', ChoiceType::class, [
                'data' => $user->isVerified(),
                'mapped' => false,
                'label' => 'Verified Regular',
                'choices' => [$this->translator->trans('Yes') => true, $this->translator->trans('No') => false],
            ])
            ->add('restricted', ChoiceType::class, [
                'data' => $user->isRestricted(),
                'mapped' => false,
                'label' => 'Restricted',
                'choices' => [$this->translator->trans('Yes') => true, $this->translator->trans('No') => false],
            ])
            ->add('osmConsent', ChoiceType::class, [
                'data' => $user->isOsmConsent(),
                'mapped' => false,
                'label' => 'show Maps',
                'choices' => [$this->translator->trans('Yes') => true, $this->translator->trans('No') => false],
            ])
            ->add('public', ChoiceType::class, [
                'data' => $user->isPublic(),
                'mapped' => false,
                'label' => 'isPublic',
                'choices' => [$this->translator->trans('Yes') => true, $this->translator->trans('No') => false],
            ])
            ->add('tagging', ChoiceType::class, [
                'data' => $user->isTagging(),
                'mapped' => false,
                'label' => 'allowTagging',
                'choices' => [$this->translator->trans('Yes') => true, $this->translator->trans('No') => false],
            ])
            ->add('locale', ChoiceType::class, [
                'data' => $user->getLocale(),
                'label' => 'Locale',
                'choices' => $languageList,
            ])
            ->add('status', ChoiceType::class, [
                'data' => $user->getStatus(),
                'choices' => UserStatus::getChoices($this->translator),
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
