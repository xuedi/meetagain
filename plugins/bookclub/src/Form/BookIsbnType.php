<?php declare(strict_types=1);

namespace Plugin\Bookclub\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class BookIsbnType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('isbn', TextType::class, [
                'label' => 'ISBN',
                'attr' => [
                    'placeholder' => 'e.g. 978-0-13-468599-1',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Regex(
                        pattern: '/^[\d\-X]+$/',
                        message: 'Please enter a valid ISBN (digits, hyphens, and X only)',
                    ),
                ],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
