<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Cms;
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

        $builder->add('menuLocations', ChoiceType::class, [
            'label' => 'Menu Locations',
            'choices' => MenuLocation::getChoices($this->translator),
            'multiple' => true,
            'expanded' => true,
            'required' => false,
        ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();

            $optionalFields = ['menuLocations', 'locked'];
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
        ]);
    }
}
