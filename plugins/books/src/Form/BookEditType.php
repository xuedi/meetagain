<?php declare(strict_types=1);

namespace Plugin\Books\Form;

use Override;
use Plugin\Books\Entity\Book;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookEditType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'books_book.label_title',
                'required' => true,
                'attr' => ['class' => 'input'],
            ])
            ->add('author', TextType::class, [
                'label' => 'books_book.label_author',
                'required' => false,
                'attr' => ['class' => 'input'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'books_book.label_description',
                'required' => false,
                'attr' => ['class' => 'textarea', 'rows' => 6],
            ])
            ->add('pageCount', IntegerType::class, [
                'label' => 'books_book.label_page_count',
                'required' => false,
                'attr' => ['class' => 'input'],
            ])
            ->add('publishedYear', IntegerType::class, [
                'label' => 'books_book.label_published_year',
                'required' => false,
                'attr' => ['class' => 'input'],
            ])
            ->add('coverFile', FileType::class, [
                'label' => 'books_book.label_cover',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File(maxSize: '8000k', mimeTypes: ['image/*'], mimeTypesMessage: $this->translator->trans('books_book.flash_invalid_image')),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'books_book.button_save',
                'attr' => ['class' => 'button is-primary'],
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Book::class,
        ]);
    }
}
