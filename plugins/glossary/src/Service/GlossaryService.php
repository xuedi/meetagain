<?php declare(strict_types=1);

namespace Plugin\Glossary\Service;

use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Entity\Suggestion;
use Plugin\Glossary\Entity\SuggestionField;
use Plugin\Glossary\Filter\GlossaryFilterService;
use Plugin\Glossary\Repository\GlossaryRepository;
use RuntimeException;

readonly class GlossaryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private GlossaryRepository $repo,
        private GlossaryFilterService $filter,
        private EntityActionDispatcher $dispatcher,
    ) {}

    public function approveNew(int $id): void
    {
        $item = $this->get($id);
        if ($item === null) {
            return;
        }

        $item->setApproved(true);
        $this->em->persist($item);
        $this->em->flush();
    }

    public function deleteNew(int $id): void
    {
        $item = $this->get($id);
        if ($item === null) {
            return;
        }
        if ($item->getApproved()) {
            throw new RuntimeException('Cannot delete approved item');
        }

        $this->em->remove($item);
        $this->em->flush();
        $this->dispatcher->dispatch(EntityAction::DeleteGlossary, $id);
    }

    public function delete(int $id): void
    {
        $item = $this->get($id);
        if ($item === null) {
            return;
        }

        $this->em->remove($item);
        $this->em->flush();
        $this->dispatcher->dispatch(EntityAction::DeleteGlossary, $id);
    }

    /**
     * Update glossary directly (manager permission required).
     */
    public function update(Glossary $newGlossary, int $id): void
    {
        $current = $this->get($id);
        if ($current === null) {
            return;
        }

        $current->setPhrase($newGlossary->getPhrase());
        $current->setPinyin($newGlossary->getPinyin());
        $current->setCategory($newGlossary->getCategory());
        $current->setExplanation($newGlossary->getExplanation());

        $this->em->persist($current);
        $this->em->flush();
    }

    /**
     * Generate suggestions for changes (regular user).
     */
    public function generateSuggestions(Glossary $newGlossary, int $id, int $userId): void
    {
        $current = $this->get($id);
        if ($current === null) {
            return;
        }

        $timestamp = new DateTimeImmutable();

        if ($current->getPhrase() !== $newGlossary->getPhrase()) {
            $current->addSuggestions(Suggestion::fromParams(
                createdBy: $userId,
                createdAt: $timestamp,
                field: SuggestionField::Phrase,
                value: $newGlossary->getPhrase(),
            ));
        }

        if ($current->getPinyin() !== $newGlossary->getPinyin()) {
            $current->addSuggestions(Suggestion::fromParams(
                createdBy: $userId,
                createdAt: $timestamp,
                field: SuggestionField::Pinyin,
                value: (string) $newGlossary->getPinyin(),
            ));
        }

        if ($current->getCategory() !== $newGlossary->getCategory()) {
            $current->addSuggestions(Suggestion::fromParams(
                createdBy: $userId,
                createdAt: $timestamp,
                field: SuggestionField::Category,
                value: (string) $newGlossary->getCategory(),
            ));
        }

        if ($current->getExplanation() !== $newGlossary->getExplanation()) {
            $current->addSuggestions(Suggestion::fromParams(
                createdBy: $userId,
                createdAt: $timestamp,
                field: SuggestionField::Explanation,
                value: $newGlossary->getExplanation(),
            ));
        }

        $this->em->persist($current);
        $this->em->flush();
    }

    /**
     * Create new glossary entry.
     *
     * @param bool $autoApprove Whether to auto-approve (for managers)
     */
    public function create(Glossary $glossary, int $userId, bool $autoApprove = false): void
    {
        $glossary->setCreatedBy($userId);
        $glossary->setCreatedAt(new DateTimeImmutable());
        $glossary->setApproved($autoApprove);

        $this->em->persist($glossary);
        $this->em->flush();

        $this->dispatcher->dispatch(EntityAction::CreateGlossary, (int) $glossary->getId());
    }

    public function applySuggestion(int $id, string $hash): int
    {
        $item = $this->get($id);
        if ($item === null) {
            throw new RuntimeException('Item not found');
        }

        $suggestion = $item->getSuggestion($hash);
        switch ($suggestion->field) {
            case SuggestionField::Phrase:
                $item->setPhrase($suggestion->value);
                break;
            case SuggestionField::Pinyin:
                $item->setPinyin($suggestion->value === '' ? null : $suggestion->value);
                break;
            case SuggestionField::Category:
                $item->setCategory($suggestion->value === '' ? null : (int) $suggestion->value);
                break;
            case SuggestionField::Explanation:
                $item->setExplanation($suggestion->value);
                break;
        }
        $leftOver = $item->removeSuggestion($hash);
        $this->em->persist($item);
        $this->em->flush();

        return $leftOver;
    }

    public function denySuggestion(int $id, string $hash): int
    {
        $item = $this->get($id);
        if ($item === null) {
            throw new RuntimeException('Item not found');
        }

        $leftOver = $item->removeSuggestion($hash);
        $this->em->persist($item);
        $this->em->flush();

        return $leftOver;
    }

    public function get(int $id): ?Glossary
    {
        return $this->repo->findOneAllowed($id, $this->filter->getAllowedGlossaryIds());
    }

    /** @return Glossary[] */
    public function getList(): array
    {
        return $this->repo->findAllowed($this->filter->getAllowedGlossaryIds(), ['phrase' => 'ASC']);
    }

    public function detach(Glossary $newGlossary): void
    {
        $this->em->detach($newGlossary);
    }
}
