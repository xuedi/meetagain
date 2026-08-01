<?php declare(strict_types=1);

namespace Plugin\Glossary\Service;

use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Enum\ItemAction;
use App\Item\ActionDispatcher;
use App\Item\AdminFilterService;
use App\Item\FilterService;
use App\Item\Tag\TagService;
use App\Review\ChangeProposalService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Repository\GlossaryRepository;
use Plugin\Glossary\Review\GlossaryChangeTarget;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;

readonly class GlossaryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private GlossaryRepository $repo,
        private FilterService $itemFilter,
        private AdminFilterService $adminItemFilter,
        private EntityActionDispatcher $dispatcher,
        private TagService $tagService,
        private ActionDispatcher $itemActionDispatcher,
        private ChangeProposalService $changeProposalService,
        private Security $security,
    ) {}

    /** @return list<int> */
    public function getTagIds(int $id): array
    {
        return $this->tagService->getTagIds(GlossaryTaggableTypeProvider::ITEM_TYPE, $id);
    }

    /** @param list<int> $tagIds */
    public function encodeTagIds(array $tagIds): ?string
    {
        sort($tagIds);

        return $tagIds === [] ? null : implode(',', $tagIds);
    }

    /** @return list<int> */
    public function decodeTagIds(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_values(array_map(intval(...), array_filter(explode(',', $value), static fn(string $id): bool => trim($id) !== '')));
    }

    public function approveNew(int $id): void
    {
        $item = $this->findIncludingUnapproved($id);
        if ($item === null) {
            return;
        }

        $item->setApproved(true);
        $this->em->persist($item);
        $this->em->flush();
    }

    public function deleteNew(int $id): void
    {
        $item = $this->findIncludingUnapproved($id);
        if ($item === null) {
            return;
        }
        if ($item->getApproved()) {
            throw new RuntimeException('Cannot delete approved item');
        }

        $this->em->remove($item);
        $this->em->flush();
        $this->dispatcher->dispatch(EntityAction::DeleteGlossary, $id);
        $this->itemActionDispatcher->dispatch(ItemAction::Deleted, GlossaryTaggableTypeProvider::ITEM_TYPE, $id);
        $this->changeProposalService->removeForTarget(GlossaryTaggableTypeProvider::ITEM_TYPE, $id);
    }

    public function delete(int $id): void
    {
        $item = $this->findIncludingUnapproved($id);
        if ($item === null) {
            return;
        }

        $this->em->remove($item);
        $this->em->flush();
        $this->dispatcher->dispatch(EntityAction::DeleteGlossary, $id);
        $this->itemActionDispatcher->dispatch(ItemAction::Deleted, GlossaryTaggableTypeProvider::ITEM_TYPE, $id);
        $this->changeProposalService->removeForTarget(GlossaryTaggableTypeProvider::ITEM_TYPE, $id);
    }

    /** @param list<int> $tagIds */
    public function update(Glossary $newGlossary, int $id, array $tagIds): void
    {
        $current = $this->findIncludingUnapproved($id);
        if ($current === null) {
            return;
        }

        $current->setPhrase($newGlossary->getPhrase());
        $current->setPinyin($newGlossary->getPinyin());
        $current->setExplanation($newGlossary->getExplanation());

        $this->em->persist($current);
        $this->em->flush();

        $this->setTags($id, $tagIds);
    }

    public function applyChange(int $id, string $field, ?string $value): void
    {
        $item = $this->findIncludingUnapproved($id);
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
            case GlossaryChangeTarget::FIELD_TAG:
                $this->setTags($id, $this->decodeTagIds($value));

                return;
            default:
                throw new InvalidArgumentException(sprintf('Unknown glossary field "%s"', $field));
        }

        $this->em->persist($item);
        $this->em->flush();
    }

    /**
     * @param bool      $autoApprove Whether to auto-approve (for managers)
     * @param list<int> $tagIds
     */
    public function create(Glossary $glossary, int $userId, bool $autoApprove = false, array $tagIds = []): void
    {
        $glossary->setCreatedBy($userId);
        $glossary->setCreatedAt(new DateTimeImmutable());
        $glossary->setApproved($autoApprove);

        $this->em->persist($glossary);
        $this->em->flush();

        $this->setTags((int) $glossary->getId(), $tagIds);

        $this->dispatcher->dispatch(EntityAction::CreateGlossary, (int) $glossary->getId());
        $this->itemActionDispatcher->dispatch(ItemAction::Created, GlossaryTaggableTypeProvider::ITEM_TYPE, (int) $glossary->getId());
    }

    public function get(int $id): ?Glossary
    {
        return $this->repo->findOneAllowed($id, $this->allowedIds(), !$this->canSeeUnapproved());
    }

    public function getManaged(int $id): ?Glossary
    {
        return $this->repo->findOneAllowed($id, $this->managedIds(), !$this->canSeeUnapproved());
    }

    /** @return Glossary[] */
    public function getList(): array
    {
        return $this->repo->findAllowed($this->allowedIds(), ['phrase' => 'ASC'], !$this->canSeeUnapproved());
    }

    public function detach(Glossary $newGlossary): void
    {
        $this->em->detach($newGlossary);
    }

    /** @param list<int> $tagIds */
    private function setTags(int $id, array $tagIds): void
    {
        $this->tagService->setTags(GlossaryTaggableTypeProvider::ITEM_TYPE, $id, $tagIds);
    }

    private function findIncludingUnapproved(int $id): ?Glossary
    {
        return $this->repo->findOneAllowed($id, $this->managedIds());
    }

    /** @return list<int>|null */
    private function allowedIds(): ?array
    {
        return $this->itemFilter->getAllowedItemIds(GlossaryTaggableTypeProvider::ITEM_TYPE);
    }

    /** @return list<int>|null */
    private function managedIds(): ?array
    {
        return $this->adminItemFilter->getAllowedItemIds(GlossaryTaggableTypeProvider::ITEM_TYPE);
    }

    private function canSeeUnapproved(): bool
    {
        return $this->security->isGranted('ROLE_ORGANIZER');
    }
}
