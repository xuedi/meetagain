<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Event;
use App\Entity\EventIntervals;
use App\Entity\EventTypes;
use App\Entity\Host;
use App\Entity\Location;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('initial', HiddenType::class, [
                'data' => true,
            ])
            ->add('start', DateTimeType::class, [
                'widget' => 'single_text',
            ])
            ->add('stop', DateTimeType::class, [
                'widget' => 'single_text',
            ])
            //->add('recurringOf')
            ->add(
                'recurringRule', EnumType::class, [
                    'class' => EventIntervals::class,
                    'label' => 'Recurring',
                    'placeholder' => 'NonRecurring',
                    'required' => false,
                    'expanded' => false,
                    'multiple' => false,
                ]
            )
            ->add(
                'type', EnumType::class, [
                    'class' => EventTypes::class,
                    'label' => 'Type',
                    'placeholder' => 'Types',
                    'required' => false,
                    'expanded' => false,
                    'multiple' => false,
                ]
            )
            ->add('description', TextareaType::class, [
                'attr' => ['rows' => 16],
            ])
            ->add('name', TextType::class, [
                'label' => 'Titel'
            ])
            ->add('location', EntityType::class, [
                'class' => Location::class,
                'choice_label' => 'name',
            ])
            ->add('host', EntityType::class, [
                'class' => Host::class,
                'choice_label' => 'name',
                'label' => 'Hosts',
                'expanded' => true,
                'multiple' => true,
            ])->add('image', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Preview Image',
                'constraints' => [
                    new File([
                        'maxSize' => '5000k',
                        'mimeTypes' => [
                            'image/*',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image, preferable 16x9 format',
                    ])
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
