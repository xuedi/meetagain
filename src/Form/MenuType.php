<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Cms;
use App\Entity\Menu;
use App\Entity\MenuTranslation;
use App\Entity\MenuType as EnumMenuType;
use App\Entity\MenuRoutes as EnumMenuRoutes;
use App\Entity\MenuLocation as EnumMenuLocation;
use App\Entity\MenuVisibility as EnumMenuVisibility;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Repository\MenuTranslationRepository;
use App\Service\TranslationService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuType extends AbstractType
{
    public function __construct(
        private readonly TranslationService $translationService,
        private readonly TranslatorInterface $translator,
        private readonly MenuTranslationRepository $menuTransRepo,
        private readonly CmsRepository $cmsRepo,
        private readonly EventRepository $eventRepo,
    )
    {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $menu = $options['data'] ?? null;
        $typeSelect = $menu?->getType() ?? EnumMenuType::Cms;
        $locationSelect = $menu?->getLocation() ?? EnumMenuLocation::TopBar;
        $visibilitySelect = $menu?->getVisibility() ?? EnumMenuVisibility::Everyone;
        $builder->add('type', ChoiceType::class, [
            'mapped' => false,
            'label' => 'Type',
            'choices' => EnumMenuType::getChoices($this->translator),
            'data' => $typeSelect->value,
        ]);
        $builder->add('location', ChoiceType::class, [
            'mapped' => false,
            'label' => 'Location',
            'choices' => EnumMenuLocation::getChoices($this->translator),
            'data' => $locationSelect->value,
        ]);
        $builder->add('visibility', ChoiceType::class, [
            'mapped' => false,
            'label' => 'Visibility',
            'choices' => EnumMenuVisibility::getChoices($this->translator),
            'data' => $visibilitySelect->value,
        ]);
        $builder->add('slug', TextType::class, [
            'disabled' => !($typeSelect == EnumMenuType::Url),
            'label' => 'Url for external links',
            'required' => false
        ]);
        $builder->add('cms', ChoiceType::class, [
            'disabled' => !($typeSelect == EnumMenuType::Cms),
            'mapped' => false,
            'label' => 'Cms pages',
            'choices' => $this->cmsRepo->getChoices(),
            'data' => $menu?->getCms()?->getId() ?? 1,
        ]);
        $builder->add('event', ChoiceType::class, [
            'disabled' => !($typeSelect == EnumMenuType::Event),
            'mapped' => false,
            'label' => 'Events',
            'choices' => $this->eventRepo->getChoices('en'), // TODO: inject actual current locale
            'data' => $menu?->getEvent()?->getId() ?? 1,
        ]);
        $builder->add('route', ChoiceType::class, [
            'disabled' => !($typeSelect == EnumMenuType::Route),
            'mapped' => false,
            'label' => 'AppRoute',
            'choices' => EnumMenuRoutes::getChoices($this->translator),
            'data' => $menu?->getRoute()?->value ?? EnumMenuRoutes::Profile->value,
        ]);

        $menuId = $menu?->getId() ?? null;
        foreach ($this->translationService->getLanguageCodes() as $languageCode) {
            $crit = ['menu' => $menuId, 'language' => $languageCode];
            $translation = $this->menuTransRepo->findOneBy($crit) ?? new MenuTranslation();
            $builder->add("name-$languageCode", TextType::class, [
                'label' => "$languageCode",
                'data' => $translation?->getName() ?? '',
                'mapped' => false,
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Menu::class,
        ]);
    }
}
