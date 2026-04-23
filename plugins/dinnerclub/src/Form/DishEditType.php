<?php

declare(strict_types=1);

namespace Plugin\Dinnerclub\Form;

use App\Entity\PronunciationSystem;
use Plugin\Dinnerclub\Entity\Dish;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class DishEditType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('pronunciationSystem', EntityType::class, [
            'label' => 'dinnerclub.field_pronunciation_system',
            'class' => PronunciationSystem::class,
            'choice_label' => static fn(PronunciationSystem $s) => $s->getName() . ' (' . $s->getLanguage() . ')',
            'required' => false,
            'placeholder' => 'dinnerclub.field_pronunciation_system_placeholder',
        ])->add('phonetic', TextType::class, [
            'label' => 'dinnerclub.field_phonetic',
            'required' => false,
            'attr' => ['placeholder' => $this->translator->trans('dinnerclub.field_phonetic_example')],
        ])->add('origin', TextType::class, [
            'label' => 'dinnerclub.field_origin',
            'required' => false,
            'attr' => [
                'placeholder' => $this->translator->trans('dinnerclub.field_origin_placeholder'),
            ],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dish::class,
        ]);
    }
}
