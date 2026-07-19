<?php declare(strict_types=1);

namespace Plugin\Glossary\Form;

use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Service\ConfigService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GlossaryType extends AbstractType
{
    public function __construct(
        private readonly ConfigService $configService,
    ) {}

    #[\Override]
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

        if ($config->hasCategories()) {
            $choices = [];
            foreach ($config->getCategoryMap() as $id => $label) {
                $choices[$label] = $id;
            }

            $builder->add('category', ChoiceType::class, [
                'label' => 'glossary.label_category',
                'choices' => $choices,
                'required' => false,
                'placeholder' => '',
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Glossary::class,
        ]);
    }
}
