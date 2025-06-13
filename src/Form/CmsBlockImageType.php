<?php declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Contracts\Translation\TranslatorInterface;

class CmsBlockImageType extends AbstractType
{
    public function __construct(readonly TranslatorInterface $translator)
    {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('imageUpload', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '10M',
                        'minWidth' => 432,
                        'minHeight' => 432,
                        'mimeTypes' => [
                            'image/*',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image, preferable a square format',
                    ])
                ],
            ])
            ->add('blockId', IntegerType::class, [
                'mapped' => false,
                'required' => true,
                'label' => false,
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => false
        ]);
    }
}
