<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Cms;
use App\Entity\CmsLinkName;
use App\Entity\CmsMenuLocation;
use App\Entity\CmsTitle;
use App\Entity\MenuLocation;
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
                $this->translator->trans('Published') => 1,
                $this->translator->trans('Draft') => 0,
            ],
        ]);

        if ($options['is_admin']) {
            $builder->add('locked', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    $this->translator->trans('Locked (visible on all groups)') => 1,
                    $this->translator->trans('Normal') => 0,
                ],
                'help' => 'Locked pages are visible on all multisite groups (e.g., imprint, privacy)',
            ]);
        }

        $builder->add('pageTitle', TextType::class, [
            'label' => 'Page Title',
            'required' => false,
            'mapped' => false,
            'help' => 'Title shown in browser and page content',
        ])->add('linkName', TextType::class, [
            'label' => 'Menu Link Name',
            'required' => false,
            'mapped' => false,
            'help' => 'Name shown in menus (defaults to page title if empty)',
        ]);

        $builder->add('menuLocations', ChoiceType::class, [
            'label' => 'Menu Locations',
            'choices' => MenuLocation::getChoices($this->translator),
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'mapped' => false,
        ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options): void {
            $cms = $event->getData();
            if (!$cms instanceof Cms) {
                return;
            }

            $locale = $options['edit_locale'];
            $form = $event->getForm();

            // Populate menuLocations
            $values = [];
            foreach ($cms->getMenuLocations() as $ml) {
                $values[] = $ml->getLocation()->value;
            }
            $form->get('menuLocations')->setData($values);

            // Populate pageTitle
            foreach ($cms->getTitles() as $title) {
                if ($title->getLanguage() === $locale) {
                    $form->get('pageTitle')->setData($title->getTitle());
                    break;
                }
            }

            // Populate linkName
            foreach ($cms->getLinkNames() as $linkName) {
                if ($linkName->getLanguage() === $locale) {
                    $form->get('linkName')->setData($linkName->getName());
                    break;
                }
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($options): void {
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
                    $existingValues[$ml->getLocation()->value] = $ml;
                }

                // Remove locations that are no longer selected
                foreach ($existingValues as $value => $ml) {
                    if (!in_array($value, $values, strict: true)) {
                        $cms->removeMenuLocation($ml);
                    }
                }

                // Add new locations that don't exist yet
                foreach ($values as $value) {
                    if (!isset($existingValues[$value])) {
                        $location = MenuLocation::from($value);
                        $menuLocation = new CmsMenuLocation();
                        $menuLocation->setCms($cms);
                        $menuLocation->setLocation($location);
                        $cms->addMenuLocation($menuLocation);
                    }
                }
            }

            // Handle pageTitle
            $titleText = $form->get('pageTitle')->getData();
            $existingTitle = null;
            foreach ($cms->getTitles() as $title) {
                if ($title->getLanguage() === $locale) {
                    $existingTitle = $title;
                    break;
                }
            }

            if ($titleText !== null && $titleText !== '') {
                if ($existingTitle) {
                    $existingTitle->setTitle($titleText);
                } else {
                    $newTitle = new CmsTitle();
                    $newTitle->setCms($cms);
                    $newTitle->setLanguage($locale);
                    $newTitle->setTitle($titleText);
                    $cms->addTitle($newTitle);
                }
            } elseif ($existingTitle) {
                $cms->removeTitle($existingTitle);
            }

            // Handle linkName
            $linkNameText = $form->get('linkName')->getData();
            $existingLinkName = null;
            foreach ($cms->getLinkNames() as $linkName) {
                if ($linkName->getLanguage() === $locale) {
                    $existingLinkName = $linkName;
                    break;
                }
            }

            if ($linkNameText !== null && $linkNameText !== '') {
                if ($existingLinkName) {
                    $existingLinkName->setName($linkNameText);
                } else {
                    $newLinkName = new CmsLinkName();
                    $newLinkName->setCms($cms);
                    $newLinkName->setLanguage($locale);
                    $newLinkName->setName($linkNameText);
                    $cms->addLinkName($newLinkName);
                }
            } elseif ($existingLinkName) {
                $cms->removeLinkName($existingLinkName);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();

            $optionalFields = ['locked'];
            foreach ($optionalFields as $field) {
                if (isset($data[$field]) && $this->isEmpty($data[$field])) {
                    unset($data[$field]);
                }
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
