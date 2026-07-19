<?php declare(strict_types=1);

namespace Plugin\Books\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookManualType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('isbn', TextType::class, [
            'label' => 'books_book.field_isbn',
            'required' => true,
            'attr' => ['class' => 'input'],
            'constraints' => [
                new NotBlank(),
                new Regex(pattern: '/^[\d\-\s X]+$/i', message: 'books_book.error_isbn_invalid'),
            ],
        ])->add('title', TextType::class, [
            'label' => 'books_book.label_title',
            'required' => true,
            'attr' => ['class' => 'input'],
        ])->add('author', TextType::class, [
            'label' => 'books_book.label_author',
            'required' => false,
            'attr' => ['class' => 'input'],
        ])->add('description', TextareaType::class, [
            'label' => 'books_book.label_description',
            'required' => false,
            'attr' => ['class' => 'textarea', 'rows' => 4],
        ])->add('pageCount', IntegerType::class, [
            'label' => 'books_book.label_page_count',
            'required' => false,
            'attr' => ['class' => 'input'],
        ])->add('publishedYear', IntegerType::class, [
            'label' => 'books_book.label_published_year',
            'required' => false,
            'attr' => ['class' => 'input'],
        ])->add('submit', SubmitType::class, [
            'label' => $this->translator->trans('books_book.button_submit'),
            'attr' => ['class' => 'button'],
        ]);
    }
}
