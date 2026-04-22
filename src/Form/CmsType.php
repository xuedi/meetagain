<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Cms;
use App\Entity\CmsLinkName;
use App\Entity\CmsMenuLocation;
use App\Entity\CmsTitle;
use App\Enum\MenuLocation;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class CmsType extends AbstractType
{
    public function __construct(
        public readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('slug', TextType::class, [
            'label' => false, // TODO: add SLUG restrains
        ])->add('published', ChoiceType::class, [
            'label' => false,
            'choices' => [
                $this->translator->trans('admin_cms.status_published') => 1,
                $this->translator->trans('admin_cms.status_draft') => 0,
            ],
        ]);

        if ($options['is_admin']) {
            $builder->add('locked', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    $this->translator->trans('admin_cms.status_locked') => 1,
                    $this->translator->trans('admin_cms.status_normal') => 0,
                ],
                'help' => $this->translator->trans('admin_cms.status_locked_help'),
            ]);
        }

        $builder->add('pageTitle', TextType::class, [
            'label' => $this->translator->trans('admin_cms.field_page_title'),
            'required' => false,
            'mapped' => false,
            'help' => $this->translator->trans('admin_cms.field_page_title_help'),
        ])->add('linkName', TextType::class, [
            'label' => $this->translator->trans('admin_cms.field_link_name'),
            'required' => false,
            'mapped' => false,
            'help' => $this->translator->trans('admin_cms.field_link_name_help'),
        ]);

        $builder->add('menuLocations', ChoiceType::class, [
            'label' => $this->translator->trans('admin_cms.field_menu_locations'),
            'choices' => MenuLocation::getChoices($this->translator),
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'mapped' => false,
        ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event) use ($options): void {
            $cms = $event->getData();
            if (!$cms instanceof Cms) {
                return;
            }

            $locale = $options['edit_locale'];
            $form = $event->getForm();

            // Populate menuLocations
            $values = [];
            foreach ($cms->getMenuLocations() as $ml) {
                $values[] = $ml->getLocation()?->value;
            }
            $form->get('menuLocations')->setData($values);

            // Populate pageTitle
            foreach ($cms->getTitles() as $title) {
                if ($title->getLanguage() !== $locale) {
                    continue;
                }

                $form->get('pageTitle')->setData($title->getTitle());
                break;
            }

            // Populate linkName
            foreach ($cms->getLinkNames() as $linkName) {
                if ($linkName->getLanguage() !== $locale) {
                    continue;
                }

                $form->get('linkName')->setData($linkName->getName());
                break;
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) use ($options): void {
            $cms = $event->getData();
            $form = $event->getForm();
            if (!$cms instanceof Cms) {
                return;
            }

            $locale = $options['edit_locale'];

            // Handle menuLocations
            if ($form->get('menuLocations')->isSubmitted()) {
                $values = $form->get('menuLocations')->getData() ?? [];

                // Get current location values
                $existingValues = [];
                foreach ($cms->getMenuLocations() as $ml) {
                    $existingValues[$ml->getLocation()?->value] = $ml;
                }

                // Remove locations that are no longer selected
                foreach ($existingValues as $value => $ml) {
                    if (in_array($value, $values, strict: true)) {
                        continue;
                    }

                    $cms->removeMenuLocation($ml);
                }

                // Add new locations that don't exist yet
                foreach ($values as $value) {
                    if (isset($existingValues[$value])) {
                        continue;
                    }

                    $location = MenuLocation::from($value);
                    $menuLocation = new CmsMenuLocation();
                    $menuLocation->setCms($cms);
                    $menuLocation->setLocation($location);
                    $cms->addMenuLocation($menuLocation);
                }
            }

            // Handle pageTitle
            $titleText = $form->get('pageTitle')->getData();
            $existingTitle = null;
            foreach ($cms->getTitles() as $title) {
                if ($title->getLanguage() !== $locale) {
                    continue;
                }

                $existingTitle = $title;
                break;
            }

            if ($titleText !== null && $titleText !== '') {
                if ($existingTitle === null) {
                    $existingTitle = new CmsTitle();
                    $existingTitle->setCms($cms);
                    $existingTitle->setLanguage($locale);
                    $cms->addTitle($existingTitle);
                }
                $existingTitle->setTitle($titleText);
            }
            if (($titleText === null || $titleText === '') && $existingTitle !== null) {
                $cms->removeTitle($existingTitle);
            }

            // Handle linkName
            $linkNameText = $form->get('linkName')->getData();
            $existingLinkName = null;
            foreach ($cms->getLinkNames() as $linkName) {
                if ($linkName->getLanguage() !== $locale) {
                    continue;
                }

                $existingLinkName = $linkName;
                break;
            }

            if ($linkNameText !== null && $linkNameText !== '') {
                if ($existingLinkName === null) {
                    $existingLinkName = new CmsLinkName();
                    $existingLinkName->setCms($cms);
                    $existingLinkName->setLanguage($locale);
                    $cms->addLinkName($existingLinkName);
                }
                $existingLinkName->setName($linkNameText);
            }
            if (($linkNameText === null || $linkNameText === '') && $existingLinkName !== null) {
                $cms->removeLinkName($existingLinkName);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();

            $optionalFields = ['locked'];
            foreach ($optionalFields as $field) {
                if (!(isset($data[$field]) && $this->isEmpty($data[$field]))) {
                    continue;
                }

                unset($data[$field]);
            }

            $event->setData($data);
        });
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || is_array($value) && count($value) === 0;
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cms::class,
            'is_admin' => false,
            'edit_locale' => 'en',
        ]);
    }
}
