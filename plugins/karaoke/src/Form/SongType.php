<?php declare(strict_types=1);

namespace Plugin\Karaoke\Form;

use App\Item\Tag\AssignmentFormHelper;
use Override;
use Plugin\Karaoke\Entity\Song;
use Plugin\Karaoke\Service\MediaLinkParser;
use Plugin\Karaoke\Service\SongService;
use Plugin\Karaoke\ValueObject\MediaLink;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class SongType extends AbstractType
{
    public function __construct(
        private readonly MediaLinkParser $mediaLinkParser,
        private readonly AssignmentFormHelper $assignmentFormHelper,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $song = $options['song'];
        $draft = $options['draft'];

        $builder->add('title', TextType::class, [
            'label' => 'karaoke.field_title',
            'mapped' => false,
            'data' => $song?->getTitle() ?? $draft['title'] ?? null,
            'constraints' => [new NotBlank(), new Length(max: 255)],
        ])->add('artist', TextType::class, [
            'label' => 'karaoke.field_artist',
            'required' => false,
            'mapped' => false,
            'data' => $song?->getArtist() ?? $draft['artist'] ?? null,
            'constraints' => [new Length(max: 255)],
        ])->add('language', LanguageType::class, [
            'label' => 'karaoke.field_language',
            'help' => 'karaoke.help_language',
            'mapped' => false,
            'data' => $song?->getLanguage() ?? $draft['language'] ?? null,
            'placeholder' => '',
            'preferred_choices' => ['zh', 'en', 'de', 'fr', 'es', 'ja', 'ko'],
            'choice_filter' => static fn(?string $code): bool => $code !== null && strlen($code) === 2,
            'constraints' => [new NotBlank()],
        ])->add('mediaLink', TextType::class, [
            'label' => 'karaoke.field_media_link',
            'help' => 'karaoke.help_media_link',
            'mapped' => false,
            'data' => $song?->getMediaLink() ?? $draft['link'] ?? null,
            'data_class' => null,
            'invalid_message' => 'karaoke.validator_media_link',
            'constraints' => [new NotNull(message: 'karaoke.validator_media_link')],
        ])->add('lyrics', TextareaType::class, [
            'label' => 'karaoke.field_lyrics',
            'help' => 'karaoke.help_lyrics',
            'mapped' => false,
            'required' => false,
            'data' => $options['lyrics'],
            'attr' => ['rows' => 18, 'class' => 'is-family-monospace'],
        ]);

        $builder->get('mediaLink')->addModelTransformer(new CallbackTransformer(
            static fn(?MediaLink $link): string => $link?->getWatchUrl() ?? '',
            function (?string $input): ?MediaLink {
                if ($input === null || trim($input) === '') {
                    return null;
                }

                return $this->mediaLinkParser->parse($input) ?? throw new TransformationFailedException('Unsupported media link.');
            },
        ));

        if ($song === null) {
            $builder->add('translationLanguage', ChoiceType::class, [
                'label' => 'karaoke.field_translation_language',
                'mapped' => false,
                'choices' => array_combine($options['translation_languages'], $options['translation_languages']),
                'choice_label' => static fn(string $code): string => 'language_' . $code,
                'data' => $options['translation_language'],
            ]);
        }

        $this->assignmentFormHelper->addAssignmentFields($builder, SongService::ITEM_TYPE, $song?->getId());

        $builder->add('submit', SubmitType::class, [
            'label' => 'karaoke.button_save',
            'attr' => ['class' => 'button is-link'],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'song' => null,
            'draft' => [],
            'lyrics' => '',
            'translation_language' => 'en',
            'translation_languages' => [],
        ]);
        $resolver->setAllowedTypes('song', [Song::class, 'null']);
        $resolver->setAllowedTypes('draft', 'array');
        $resolver->setAllowedTypes('lyrics', 'string');
        $resolver->setAllowedTypes('translation_language', 'string');
        $resolver->setAllowedTypes('translation_languages', 'array');
    }
}
