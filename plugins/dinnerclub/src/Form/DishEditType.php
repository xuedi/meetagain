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

class DishEditType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('pronunciationSystem', EntityType::class, [
            'label' => 'Pronunciation System',
            'class' => PronunciationSystem::class,
            'choice_label' => static fn(PronunciationSystem $s) => $s->getName() . ' (' . $s->getLanguage() . ')',
            'required' => false,
            'placeholder' => 'None',
        ])->add('phonetic', TextType::class, [
            'label' => 'Phonetic',
            'required' => false,
            'attr' => ['placeholder' => 'e.g. má po dòu fu'],
        ])->add('origin', TextType::class, [
            'label' => 'Origin / Region',
            'required' => false,
            'attr' => [
                'placeholder' => 'Optional: Where is this dish from? (e.g., Southern China, Naples, NYC, Berlin)',
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
