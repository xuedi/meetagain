<?php declare(strict_types=1);

namespace Plugin\Films\Form;

use App\Item\Taxonomy\ItemAssignmentFormHelper;
use Override;
use Plugin\Films\Entity\Film;
use Plugin\Films\Service\FilmService;
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

class FilmEditType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ItemAssignmentFormHelper $assignmentFormHelper,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => $this->translator->trans('films_film.label_title'),
                'required' => true,
                'attr' => ['class' => 'input'],
            ])
            ->add('originalTitle', TextType::class, [
                'label' => $this->translator->trans('films_film.label_original_title'),
                'required' => false,
                'attr' => ['class' => 'input'],
            ])
            ->add('year', IntegerType::class, [
                'label' => $this->translator->trans('films_film.label_year'),
                'required' => false,
                'attr' => ['class' => 'input'],
            ])
            ->add('runtime', IntegerType::class, [
                'label' => $this->translator->trans('films_film.label_runtime'),
                'required' => false,
                'attr' => ['class' => 'input'],
            ])
            ->add('description', TextareaType::class, [
                'label' => $this->translator->trans('films_film.label_description'),
                'required' => false,
                'attr' => ['class' => 'textarea', 'rows' => 6],
            ])
            ->add('genresCsv', TextType::class, [
                'label' => $this->translator->trans('films_film.label_genres'),
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'input',
                    'placeholder' => $this->translator->trans('films_film.placeholder_genres'),
                ],
                'help' => $this->translator->trans('films_film.help_genres'),
            ])
            ->add('posterFile', FileType::class, [
                'label' => $this->translator->trans('films_film.label_poster'),
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File(maxSize: '8000k', mimeTypes: ['image/*'], mimeTypesMessage: $this->translator->trans('films_film.flash_invalid_image')),
                ],
            ]);

        $film = $builder->getData();
        $this->assignmentFormHelper->addAssignmentFields($builder, FilmService::ITEM_TYPE, $film instanceof Film ? $film->getId() : null);

        $builder->add('submit', SubmitType::class, [
            'label' => $this->translator->trans('films_film.button_save'),
            'attr' => ['class' => 'button is-primary'],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Film::class,
        ]);
    }
}
