<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Service\Config\LanguageService;
use Override;
use Symfony\Component\Form\AbstractType;
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

        $yes = $this->translator->trans('admin_member.choice_yes');
        $no = $this->translator->trans('admin_member.choice_no');

        $builder
            ->add('name', TextType::class, [
                'label' => 'admin_member.form_label_name',
                'constraints' => [
                    new Length(max: 64, maxMessage: 'usernames cant be longer than 64 characters (less with chinese)'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'admin_member.form_label_email',
                'constraints' => [
                    new Length(max: 180, maxMessage: 'emails cant be longer than 180 characters'),
                ],
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'admin_member.form_label_bio',
            ])
            ->add('role', ChoiceType::class, [
                'choices' => UserRole::getChoices($this->translator),
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('verified', ChoiceType::class, [
                'data' => $user->isVerified(),
                'mapped' => false,
                'label' => 'admin_member.form_label_verified',
                'choices' => [$yes => true, $no => false],
            ])
            ->add('restricted', ChoiceType::class, [
                'data' => $user->isRestricted(),
                'mapped' => false,
                'label' => 'admin_member.form_label_restricted',
                'choices' => [$yes => true, $no => false],
            ])
            ->add('osmConsent', ChoiceType::class, [
                'data' => $user->isOsmConsent(),
                'mapped' => false,
                'label' => 'admin_member.form_label_osm_consent',
                'choices' => [$yes => true, $no => false],
            ])
            ->add('public', ChoiceType::class, [
                'data' => $user->isPublic(),
                'mapped' => false,
                'label' => 'admin_member.form_label_is_public',
                'choices' => [$yes => true, $no => false],
            ])
            ->add('tagging', ChoiceType::class, [
                'data' => $user->isTagging(),
                'mapped' => false,
                'label' => 'admin_member.form_label_tagging',
                'choices' => [$yes => true, $no => false],
            ])
            ->add('locale', ChoiceType::class, [
                'data' => $user->getLocale(),
                'label' => 'admin_member.form_label_locale',
                'choices' => $languageList,
            ])
            ->add('status', ChoiceType::class, [
                'data' => $user->getStatus(),
                'choices' => UserStatus::getChoices($this->translator),
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
