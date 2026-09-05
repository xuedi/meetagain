<?php declare(strict_types=1);

namespace Plugin\Boardgames\Form;

use App\Item\Tag\AssignmentFormHelper;
use Override;
use Plugin\Boardgames\Service\GameService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Contracts\Translation\TranslatorInterface;

class GameManualType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly AssignmentFormHelper $assignmentFormHelper,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'boardgames_game.label_name',
                'required' => true,
                'attr' => ['class' => 'input'],
                'constraints' => [new NotBlank(message: 'boardgames_game.validator_name_blank')],
            ])
            ->add('yearPublished', IntegerType::class, [
                'label' => 'boardgames_game.label_year',
                'required' => false,
                'attr' => ['class' => 'input'],
            ])
            ->add('minPlayers', IntegerType::class, [
                'label' => 'boardgames_game.label_min_players',
                'required' => false,
                'attr' => ['class' => 'input'],
                'constraints' => [new Positive(message: 'boardgames_game.validator_players_positive')],
            ])
            ->add('maxPlayers', IntegerType::class, [
                'label' => 'boardgames_game.label_max_players',
                'required' => false,
                'attr' => ['class' => 'input'],
                'constraints' => [new Positive(message: 'boardgames_game.validator_players_positive')],
            ])
            ->add('minPlaytime', IntegerType::class, [
                'label' => 'boardgames_game.label_min_playtime',
                'required' => false,
                'attr' => ['class' => 'input'],
            ])
            ->add('maxPlaytime', IntegerType::class, [
                'label' => 'boardgames_game.label_max_playtime',
                'required' => false,
                'attr' => ['class' => 'input'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'boardgames_game.label_description',
                'required' => false,
                'attr' => ['class' => 'textarea', 'rows' => 4],
            ]);

        $this->assignmentFormHelper->addAssignmentFields($builder, GameService::ITEM_TYPE, null);

        $builder->add('submit', SubmitType::class, [
            'label' => $this->translator->trans('boardgames_game.button_submit'),
            'attr' => ['class' => 'button'],
        ]);
    }
}
