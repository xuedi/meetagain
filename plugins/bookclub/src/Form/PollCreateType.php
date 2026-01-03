<?php declare(strict_types=1);

namespace Plugin\Bookclub\Form;

use App\Entity\Event;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Entity\BookSuggestion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PollCreateType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var BookSuggestion[] $suggestions */
        $suggestions = $options['suggestions'];
        /** @var Book[] $books */
        $books = $options['books'];
        /** @var Event[] $events */
        $events = $options['events'];

        $eventChoices = ['' => null];
        foreach ($events as $event) {
            $label = sprintf('%s - %s', $event->getStart()->format('Y-m-d'), $event->getTitle('en'));
            $eventChoices[$label] = $event->getId();
        }

        $suggestionChoices = [];
        foreach ($suggestions as $suggestion) {
            $book = $suggestion->getBook();
            $label = sprintf('%s - %s (Priority: %.0f)',
                $book->getTitle(),
                $book->getAuthor() ?? 'Unknown',
                $options['suggestion_service'] ? $options['suggestion_service']->calculatePriority($suggestion) : 0
            );
            $suggestionChoices[$label] = $suggestion->getId();
        }

        $bookChoices = [];
        foreach ($books as $book) {
            $label = sprintf('%s - %s', $book->getTitle(), $book->getAuthor() ?? 'Unknown');
            $bookChoices[$label] = $book->getId();
        }

        $builder
            ->add('event_id', ChoiceType::class, [
                'label' => 'For Event',
                'choices' => $eventChoices,
                'required' => false,
                'placeholder' => '-- Select an event --',
            ])
            ->add('title', TextType::class, [
                'label' => 'Poll Title',
                'attr' => [
                    'placeholder' => 'e.g. January 2025 Book Selection',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('suggestions', ChoiceType::class, [
                'label' => 'Member Suggestions',
                'choices' => $suggestionChoices,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('books', ChoiceType::class, [
                'label' => 'Add Books Directly',
                'choices' => $bookChoices,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'suggestions' => [],
            'books' => [],
            'events' => [],
            'suggestion_service' => null,
        ]);
    }
}
