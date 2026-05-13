<?php declare(strict_types=1);

namespace Plugin\Filmclub\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class NoteType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('body', TextareaType::class, [
                'label' => $this->translator->trans('filmclub_note.label_body'),
                'required' => true,
                'attr' => ['rows' => 5],
            ])
            ->add('revealToGroup', CheckboxType::class, [
                'label' => $this->translator->trans('filmclub_note.label_reveal'),
                'required' => false,
                'help' => $this->translator->trans('filmclub_note.help_reveal'),
            ])
            ->add('submit', SubmitType::class, [
                'label' => $this->translator->trans('filmclub_note.button_save'),
                'attr' => ['class' => 'button is-primary'],
            ]);
    }
}
