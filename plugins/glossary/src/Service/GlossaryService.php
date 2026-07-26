<?php declare(strict_types=1);

namespace Plugin\Glossary\Service;

use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Enum\ItemAction;
use App\Item\ActionDispatcher;
use App\Item\FilterService;
use App\Item\Taxonomy\TaxonomyService;
use App\Review\ChangeProposalService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryCategorizableTypeProvider;
use Plugin\Glossary\Repository\GlossaryRepository;
use Plugin\Glossary\Review\GlossaryChangeTarget;
use RuntimeException;

readonly class GlossaryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private GlossaryRepository $repo,
        private FilterService $itemFilter,
        private EntityActionDispatcher $dispatcher,
        private TaxonomyService $taxonomyService,
        private ActionDispatcher $itemActionDispatcher,
        private ChangeProposalService $changeProposalService,
    ) {}

    public function getCategory(int $id): ?int
    {
        return $this->taxonomyService->getCategory(GlossaryCategorizableTypeProvider::ITEM_TYPE, $id);
    }

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
        $this->itemActionDispatcher->dispatch(ItemAction::Deleted, GlossaryCategorizableTypeProvider::ITEM_TYPE, $id);
        $this->changeProposalService->removeForTarget(GlossaryCategorizableTypeProvider::ITEM_TYPE, $id);
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
        $this->itemActionDispatcher->dispatch(ItemAction::Deleted, GlossaryCategorizableTypeProvider::ITEM_TYPE, $id);
        $this->changeProposalService->removeForTarget(GlossaryCategorizableTypeProvider::ITEM_TYPE, $id);
    }

    public function update(Glossary $newGlossary, int $id, ?int $categoryId): void
    {
        $current = $this->get($id);
        if ($current === null) {
            return;
        }

        $current->setPhrase($newGlossary->getPhrase());
        $current->setPinyin($newGlossary->getPinyin());
        $current->setExplanation($newGlossary->getExplanation());

        $this->em->persist($current);
        $this->em->flush();

        $this->taxonomyService->setCategory(GlossaryCategorizableTypeProvider::ITEM_TYPE, $id, $categoryId);
    }

    public function applyChange(int $id, string $field, ?string $value): void
    {
        $item = $this->get($id);
        if ($item === null) {
            throw new RuntimeException('Item not found');
        }

        switch ($field) {
            case GlossaryChangeTarget::FIELD_PHRASE:
                $item->setPhrase((string) $value);
                break;
            case GlossaryChangeTarget::FIELD_PINYIN:
                $item->setPinyin($value === null || $value === '' ? null : $value);
                break;
            case GlossaryChangeTarget::FIELD_EXPLANATION:
                $item->setExplanation((string) $value);
                break;
            case GlossaryChangeTarget::FIELD_CATEGORY:
                $this->taxonomyService->setCategory(
                    GlossaryCategorizableTypeProvider::ITEM_TYPE,
                    $id,
                    $value === null || $value === '' ? null : (int) $value,
                );
                return;
            default:
                throw new InvalidArgumentException(sprintf('Unknown glossary field "%s"', $field));
        }

        $this->em->persist($item);
        $this->em->flush();
    }

    /**
     * @param bool $autoApprove Whether to auto-approve (for managers)
     */
    public function create(Glossary $glossary, int $userId, bool $autoApprove = false, ?int $categoryId = null): void
    {
        $glossary->setCreatedBy($userId);
        $glossary->setCreatedAt(new DateTimeImmutable());
        $glossary->setApproved($autoApprove);

        $this->em->persist($glossary);
        $this->em->flush();

        $this->taxonomyService->setCategory(GlossaryCategorizableTypeProvider::ITEM_TYPE, (int) $glossary->getId(), $categoryId);

        $this->dispatcher->dispatch(EntityAction::CreateGlossary, (int) $glossary->getId());
        $this->itemActionDispatcher->dispatch(ItemAction::Created, GlossaryCategorizableTypeProvider::ITEM_TYPE, (int) $glossary->getId());
    }

    public function get(int $id): ?Glossary
    {
        return $this->repo->findOneAllowed($id, $this->itemFilter->getAllowedItemIds(GlossaryCategorizableTypeProvider::ITEM_TYPE));
    }

    /** @return Glossary[] */
    public function getList(): array
    {
        return $this->repo->findAllowed($this->itemFilter->getAllowedItemIds(GlossaryCategorizableTypeProvider::ITEM_TYPE), ['phrase' => 'ASC']);
    }

    public function detach(Glossary $newGlossary): void
    {
        $this->em->detach($newGlossary);
    }
}
