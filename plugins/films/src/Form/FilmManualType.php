<?php declare(strict_types=1);

namespace Plugin\Films\Form;

use App\Item\Tag\AssignmentFormHelper;
use Plugin\Films\Service\FilmService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilmManualType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly AssignmentFormHelper $assignmentFormHelper,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => $this->translator->trans('films_film.label_title'),
            'required' => true,
            'attr' => ['class' => 'input'],
        ])->add('year', IntegerType::class, [
            'label' => $this->translator->trans('films_film.label_year'),
            'required' => false,
            'attr' => ['class' => 'input'],
        ])->add('runtime', IntegerType::class, [
            'label' => $this->translator->trans('films_film.label_runtime'),
            'required' => false,
            'attr' => ['class' => 'input'],
        ])->add('description', TextareaType::class, [
            'label' => $this->translator->trans('films_film.label_description'),
            'required' => false,
            'attr' => ['class' => 'textarea', 'rows' => 4],
        ]);

        $this->assignmentFormHelper->addAssignmentFields($builder, FilmService::ITEM_TYPE, null);

        $builder->add('submit', SubmitType::class, [
            'label' => $this->translator->trans('films_film.button_submit'),
            'attr' => ['class' => 'button'],
        ]);
    }
}
