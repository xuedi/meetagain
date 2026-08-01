<?php declare(strict_types=1);

namespace Plugin\Glossary\Form;

use App\Item\Tag\AssignmentFormHelper;
use Override;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Service\ConfigService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GlossaryType extends AbstractType
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly AssignmentFormHelper $assignmentFormHelper,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $config = $this->configService->getConfig();

        $builder->add('phrase', TextType::class, [
            'label' => $config->getPrimaryLabel() ?? 'glossary.label_phrase',
        ])->add('explanation', TextareaType::class, [
            'label' => $config->getDefinitionLabel() ?? 'glossary.label_explanation',
        ]);

        if ($config->isSecondaryEnabled()) {
            $builder->add('pinyin', TextType::class, [
                'label' => $config->getSecondaryLabel() ?? 'glossary.label_pinyin',
                'required' => false,
            ]);
        }

        $entry = $builder->getData();
        $this->assignmentFormHelper->addAssignmentFields(
            $builder,
            GlossaryTaggableTypeProvider::ITEM_TYPE,
            $entry instanceof Glossary ? $entry->getId() : null,
        );
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Glossary::class,
        ]);
    }
}
