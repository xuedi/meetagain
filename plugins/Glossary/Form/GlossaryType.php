<?php declare(strict_types=1);

namespace Plugin\Glossary\Form;

use Plugin\Glossary\Entity\Category;
use Plugin\Glossary\Entity\Glossary;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class GlossaryType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('phrase', TextType::class)
            ->add('pinyin', TextType::class)
            ->add('explanation', TextareaType::class)
            ->add('category', ChoiceType::class, [
                'data' => $builder->getData()?->getCategory(),
                'choices' => Category::getChoices($this->translator),
                ]
            );
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Glossary::class,
        ]);
    }
}
