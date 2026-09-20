<?php declare(strict_types=1);

namespace Plugin\Glossary\Form;

use App\Item\Tag\AssignmentFormHelper;
use App\Item\TranslationFormHelper;
use Override;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Service\ConfigService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class GlossaryType extends AbstractType
{
    private const string DEFINITION_FIELD = 'definition';

    public function __construct(
        private readonly ConfigService $configService,
        private readonly AssignmentFormHelper $assignmentFormHelper,
        private readonly TranslationFormHelper $translationFormHelper,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $config = $this->configService->getConfig();
        $entry = $builder->getData();
        $current = $entry instanceof Glossary ? $entry->getSubmittedDefinitions() ?? $entry->getDefinitionMap() : [];

        $builder->add('phrase', TextType::class, [
            'label' => $config->getPrimaryLabel() ?? 'glossary.label_phrase',
        ]);

        if ($config->isSecondaryEnabled()) {
            $builder->add('secondary', TextType::class, [
                'label' => $config->getSecondaryLabel() ?? 'glossary.label_secondary',
                'required' => false,
            ]);
        }

        $this->translationFormHelper->addTranslatedFields(
            $builder,
            [
                self::DEFINITION_FIELD => [
                    TextareaType::class,
                    [
                        'label' => $config->getDefinitionLabel() ?? 'glossary.label_definition',
                        'required' => false,
                        'attr' => ['rows' => 3],
                    ],
                ],
            ],
            static fn(string $code): string => $current[$code] ?? '',
        );

        $this->assignmentFormHelper->addAssignmentFields(
            $builder,
            GlossaryTaggableTypeProvider::ITEM_TYPE,
            $options['entry_id'] ?? ($entry instanceof Glossary ? $entry->getId() : null),
        );

        $builder->addEventListener(FormEvents::POST_SUBMIT, fn(FormEvent $event) => $this->collectDefinitions($event->getForm()));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Glossary::class,
            'entry_id' => null,
        ]);
        $resolver->setAllowedTypes('entry_id', ['int', 'null']);
    }

    private function collectDefinitions(FormInterface $form): void
    {
        $entry = $form->getData();
        if (!$entry instanceof Glossary) {
            return;
        }

        $submitted = [];
        foreach ($this->translationFormHelper->extractTranslations($form, [self::DEFINITION_FIELD]) as $code => $fields) {
            $submitted[$code] = trim((string) $fields[self::DEFINITION_FIELD]);
        }

        $previous = $entry->getSubmittedDefinitions() ?? $entry->getDefinitionMap();
        $untouched = array_filter(array_diff_key($previous, $submitted), static fn(string $text): bool => $text !== '');
        $entry->submitDefinitions($submitted);

        $filled = array_filter($submitted, static fn(string $text): bool => $text !== '');
        if ($filled === [] && $untouched === []) {
            $form->addError(new FormError($this->translator->trans('glossary.validator_definition_missing')));
        }
    }
}
