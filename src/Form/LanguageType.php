<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Language;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class LanguageType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Language Code',
                'constraints' => [
                    new NotBlank(),
                    new Length(exactly: 2),
                    new Regex(
                        pattern: '/^[a-z]{2}$/',
                        message: 'Language code must be 2 lowercase letters (e.g., en, de, cn)'
                    ),
                ],
                'disabled' => $options['is_edit'],
                'attr' => ['maxlength' => 2],
            ])
            ->add('name', TextType::class, [
                'label' => 'Language Name',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 64),
                ],
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Enabled',
                'required' => false,
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Sort Order',
            ])
            ->add('tileImage', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Tile Image (for frontpage)',
                'constraints' => [
                    new File(
                        maxSize: '5000k',
                        mimeTypes: ['image/*'],
                        mimeTypesMessage: 'Please upload a valid image',
                    ),
                ],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Language::class,
            'is_edit' => false,
        ]);
    }
}
