<?php declare(strict_types=1);

namespace Plugin\Glossary\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Glossary\Entity\Category;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Entity\Suggestion;
use Plugin\Glossary\Entity\SuggestionField;
use Plugin\Glossary\Repository\GlossaryRepository;
use RuntimeException;

class GlossaryService
{
    protected GlossaryRepository $repo;
    protected EntityManagerInterface $em;

    public function __construct(ManagerRegistry $doctrine)
    {
        $em = $doctrine->getManager('emGlossary');
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('emGlossary not of type EntityManagerInterface');
        }
        $this->em = $em;
        $this->repo = $this->em->getRepository(Glossary::class);
    }

    public function approveNew(int $id): void
    {
        $item = $this->repo->findOneBy(['id' => $id]);
        $item->setApproved(true);
        $this->em->persist($item);
        $this->em->flush();
    }

    public function deleteNew(int $id): void
    {
        $item = $this->repo->findOneBy(['id' => $id]);
        if ($item->getApproved()) {
            throw new RuntimeException('Cannot delete approved item');
        }

        $this->em->remove($item);
        $this->em->flush();
    }

    public function delete(int $id): void
    {
        $item = $this->repo->findOneBy(['id' => $id]);

        $this->em->remove($item);;
        $this->em->flush();
    }

    public function generateSuggestions(Glossary $newGlossary, int $id, int $userId, bool $isManager): void
    {
        $current = $this->repo->findOneBy(['id' => $id]);
        if ($isManager) {
            $current->setPhrase($newGlossary->getPhrase());
            $current->setPinyin($newGlossary->getPinyin());
            $current->setCategory($newGlossary->getCategory());

            $this->em->persist($current);
            $this->em->flush();

            return;
        }

        if ($current->getPhrase() !== $newGlossary->getPhrase()) {
            $current->addSuggestions(Suggestion::fromParams(
                createdBy: $userId,
                createdAt: new DateTimeImmutable(),
                field: SuggestionField::Phrase,
                value: $newGlossary->getPhrase(),
            ));
        }
        if ($current->getPinyin() !== $newGlossary->getPinyin()) {
            $current->addSuggestions(Suggestion::fromParams(
                createdBy: $userId,
                createdAt: new DateTimeImmutable(),
                field: SuggestionField::Pinyin,
                value: $newGlossary->getPinyin(),
            ));
        }
        if ($current->getCategory() !== $newGlossary->getCategory()) {
            $current->addSuggestions(Suggestion::fromParams(
                createdBy: $userId,
                createdAt: new DateTimeImmutable(),
                field: SuggestionField::Category,
                value: (string)$newGlossary->getCategory()->value,
            ));
        }

        $this->em->persist($current);
        $this->em->flush();
    }

    public function createNew(Glossary $glossary, int $userId): void
    {
        $glossary->setCreatedBy($userId);
        $glossary->setCreatedAt(new DateTimeImmutable());
        $glossary->setApproved(false);

        $this->em->persist($glossary);
        $this->em->flush();
    }

    public function applySuggestion(int $id, string $hash): int
    {
        $item = $this->repo->findOneBy(['id' => $id]);
        $suggestion = $item->getSuggestion($hash);
        switch ($suggestion->field) {
            case SuggestionField::Phrase:
                $item->setPhrase($suggestion->value);
                break;
            case SuggestionField::Pinyin:
                $item->setPinyin($suggestion->value);
                break;
            case SuggestionField::Category:
                $item->setCategory(Category::from((int)$suggestion->value));;
                break;
        }
        $leftOver = $item->removeSuggestion($hash);
        $this->em->persist($item);
        $this->em->flush();

        return $leftOver;
    }

    public function denySuggestion(int $id, string $hash): int
    {
        $item = $this->repo->findOneBy(['id' => $id]);
        $leftOver = $item->removeSuggestion($hash);
        $this->em->persist($item);
        $this->em->flush();

        return $leftOver;
    }

    public function get(int $id): Glossary|null
    {
        return $this->repo->findOneBy(['id' => $id]);
    }

    public function getList(): array
    {
        return $this->repo->findBy([], ['phrase' => 'ASC']);
    }

    public function detach(Glossary $newGlossary): void
    {
        $this->em->detach($newGlossary);
    }
}
