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
        $builder->add('isbn', TextType::class, [
            'label' => 'ISBN',
            'attr' => [
                'placeholder' => 'e.g. 9783442772612 or 978-3-442-77261-2',
            ],
            'constraints' => [
                new NotBlank(),
                new Regex(pattern: '/^[\d\-\s X]+$/i', message: 'Please enter a valid ISBN (digits, hyphens, spaces, and X only)'),
            ],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
