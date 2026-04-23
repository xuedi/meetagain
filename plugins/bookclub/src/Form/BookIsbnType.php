<?php declare(strict_types=1);

namespace Plugin\Bookclub\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookIsbnType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('isbn', TextType::class, [
            'label' => 'bookclub_book.field_isbn',
            'attr' => [
                'placeholder' => $this->translator->trans('bookclub_book.field_isbn_placeholder'),
            ],
            'constraints' => [
                new NotBlank(),
                new Regex(
                    pattern: '/^[\d\-\s X]+$/i',
                    message: 'bookclub_book.error_isbn_invalid',
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
