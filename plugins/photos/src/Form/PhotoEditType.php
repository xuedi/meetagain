<?php declare(strict_types=1);

namespace Plugin\Photos\Form;

use App\Item\Tag\AssignmentFormHelper;
use App\Item\TranslationFormHelper;
use Override;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Service\PhotoService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PhotoEditType extends AbstractType
{
    public function __construct(
        private readonly TranslationFormHelper $translationFormHelper,
        private readonly AssignmentFormHelper $assignmentFormHelper,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $photo = $options['photo'];

        $this->translationFormHelper->addTranslatedFields(
            $builder,
            [
                'title' => [TextType::class, ['label' => 'photos_photo.field_title', 'required' => false]],
                'description' => [TextareaType::class, ['label' => 'photos_photo.field_description', 'required' => false, 'attr' => ['rows' => 3]]],
            ],
            static fn(string $code, string $field): ?string => match ($field) {
                'title' => $photo?->findTranslation($code)?->getTitle() ?? '',
                'description' => $photo?->findTranslation($code)?->getDescription(),
                default => null,
            },
        );

        $this->assignmentFormHelper->addAssignmentFields($builder, PhotoService::ITEM_TYPE, $photo?->getId());

        $builder->add('submit', SubmitType::class, [
            'label' => 'photos_photo.button_save',
            'attr' => ['class' => 'button is-primary'],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('photo', null);
        $resolver->setAllowedTypes('photo', [Photo::class, 'null']);
    }
}
