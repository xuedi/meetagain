<?php declare(strict_types=1);

namespace Plugin\Photos\Form;

use App\Service\Media\ImageService;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventUploadType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('files', FileType::class, [
            'label' => 'photos_event.field_files',
            'help' => 'photos_event.help_files',
            'required' => true,
            'mapped' => false,
            'multiple' => true,
            'constraints' => [
                new Count(min: 1, max: ImageService::MAX_UPLOAD_FILES, minMessage: $this->translator->trans('photos_event.error_no_image')),
                new All([
                    new Image(
                        maxSize: '16000k',
                        mimeTypes: ImageService::ACCEPTED_MIME_TYPES,
                        maxPixels: ImageService::MAX_PIXELS,
                        maxWidth: ImageService::MAX_EDGE,
                        maxHeight: ImageService::MAX_EDGE,
                        mimeTypesMessage: $this->translator->trans('photos_event.error_invalid_image'),
                    ),
                ]),
            ],
        ])->add('submit', SubmitType::class, [
            'label' => 'photos_event.button_upload',
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
