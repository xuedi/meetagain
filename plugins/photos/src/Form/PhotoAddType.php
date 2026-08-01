<?php declare(strict_types=1);

namespace Plugin\Photos\Form;

use App\Item\Tag\AssignmentFormHelper;
use App\Item\TranslationFormHelper;
use Override;
use Plugin\Photos\Service\PhotoService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Contracts\Translation\TranslatorInterface;

class PhotoAddType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly TranslationFormHelper $translationFormHelper,
        private readonly AssignmentFormHelper $assignmentFormHelper,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('photoFile', FileType::class, [
            'label' => 'photos_photo.field_file',
            'help' => 'photos_photo.help_file',
            'required' => true,
            'mapped' => false,
            'constraints' => [
                new NotNull(message: $this->translator->trans('photos_photo.error_no_image')),
                new File(maxSize: '16000k', mimeTypes: ['image/*'], mimeTypesMessage: $this->translator->trans('photos_photo.error_invalid_image')),
            ],
        ]);

        $this->translationFormHelper->addTranslatedFields(
            $builder,
            [
                'title' => [TextType::class, ['label' => 'photos_photo.field_title', 'required' => false]],
                'description' => [TextareaType::class, ['label' => 'photos_photo.field_description', 'required' => false, 'attr' => ['rows' => 3]]],
            ],
            static fn(string $code, string $field): ?string => null,
        );

        $this->assignmentFormHelper->addAssignmentFields($builder, PhotoService::ITEM_TYPE, null);

        $builder->add('submit', SubmitType::class, [
            'label' => 'photos_photo.button_submit',
            'attr' => ['class' => 'button is-primary'],
        ]);
    }
}
