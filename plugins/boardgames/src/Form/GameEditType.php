<?php declare(strict_types=1);

namespace Plugin\Boardgames\Form;

use App\Item\Tag\AssignmentFormHelper;
use Override;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Service\GameService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Contracts\Translation\TranslatorInterface;

class GameEditType extends AbstractType
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
            ->add('originalName', TextType::class, [
                'label' => 'boardgames_game.label_original_name',
                'required' => false,
                'attr' => ['class' => 'input'],
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
            ])
            ->add('maxPlayers', IntegerType::class, [
                'label' => 'boardgames_game.label_max_players',
                'required' => false,
                'attr' => ['class' => 'input'],
            ])
            ->add('bestPlayerCount', IntegerType::class, [
                'label' => 'boardgames_game.label_best_player_count',
                'required' => false,
                'attr' => ['class' => 'input'],
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
            ->add('minAge', IntegerType::class, [
                'label' => 'boardgames_game.label_min_age',
                'required' => false,
                'attr' => ['class' => 'input'],
            ])
            ->add('weight', NumberType::class, [
                'label' => 'boardgames_game.label_weight',
                'required' => false,
                'scale' => 2,
                'input' => 'string',
                'attr' => ['class' => 'input', 'step' => '0.01'],
                'constraints' => [new Range(notInRangeMessage: 'boardgames_game.validator_weight_range', min: 1, max: 5)],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'boardgames_game.label_description',
                'required' => false,
                'attr' => ['class' => 'textarea', 'rows' => 6],
            ])
            ->add('boxFile', FileType::class, [
                'label' => 'boardgames_game.label_box',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File(maxSize: '8000k', mimeTypes: ['image/*'], mimeTypesMessage: $this->translator->trans('boardgames_game.flash_invalid_image')),
                ],
            ]);

        $game = $builder->getData();
        $this->assignmentFormHelper->addAssignmentFields($builder, GameService::ITEM_TYPE, $game instanceof Game ? $game->getId() : null);

        $builder->add('submit', SubmitType::class, [
            'label' => 'boardgames_game.button_save',
            'attr' => ['class' => 'button is-primary'],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Game::class,
        ]);
    }
}
