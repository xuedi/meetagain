<?php declare(strict_types=1);

namespace Plugin\Dishes\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Dishes\Entity\DishList;
use Plugin\Dishes\Repository\DishListRepository;
use RuntimeException;

readonly class DishListService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DishListRepository $repo,
    ) {
    }

    public function createList(int $userId, string $name, ?string $description, bool $isPublic): DishList
    {
        $list = new DishList();
        $list->setCreatedBy($userId);
        $list->setCreatedAt(new DateTimeImmutable());
        $list->setName($name);
        $list->setDescription($description);
        $list->setIsPublic($isPublic);

        $this->em->persist($list);
        $this->em->flush();

        return $list;
    }

    public function updateList(int $listId, int $userId, string $name, ?string $description, bool $isPublic): void
    {
        $list = $this->repo->find($listId);
        if ($list === null) {
            throw new RuntimeException('List not found');
        }

        if ($list->getCreatedBy() !== $userId) {
            throw new RuntimeException('Not authorized to edit this list');
        }

        $list->setName($name);
        $list->setDescription($description);
        $list->setIsPublic($isPublic);

        $this->em->persist($list);
        $this->em->flush();
    }

    public function deleteList(int $listId, int $userId): void
    {
        $list = $this->repo->find($listId);
        if ($list === null) {
            throw new RuntimeException('List not found');
        }

        if ($list->getCreatedBy() !== $userId) {
            throw new RuntimeException('Not authorized to delete this list');
        }

        $this->em->remove($list);
        $this->em->flush();
    }

    public function addDishToList(int $listId, int $dishId, int $userId): void
    {
        $list = $this->repo->find($listId);
        if ($list === null) {
            throw new RuntimeException('List not found');
        }

        if ($list->getCreatedBy() !== $userId) {
            throw new RuntimeException('Not authorized to modify this list');
        }

        $list->addDishId($dishId);
        $this->em->persist($list);
        $this->em->flush();
    }

    public function removeDishFromList(int $listId, int $dishId, int $userId): void
    {
        $list = $this->repo->find($listId);
        if ($list === null) {
            throw new RuntimeException('List not found');
        }

        if ($list->getCreatedBy() !== $userId) {
            throw new RuntimeException('Not authorized to modify this list');
        }

        $list->removeDishId($dishId);
        $this->em->persist($list);
        $this->em->flush();
    }

    /**
     * @return DishList[]
     */
    public function getUserLists(int $userId): array
    {
        return $this->repo->findByUser($userId);
    }

    /**
     * @return DishList[]
     */
    public function getPublicLists(): array
    {
        return $this->repo->findPublic();
    }

    /**
     * @return DishList[]
     */
    public function getPublicListsByOthers(int $currentUserId): array
    {
        return $this->repo->findPublicByOthers($currentUserId);
    }

    public function getList(int $listId): ?DishList
    {
        return $this->repo->find($listId);
    }

    public function canUserEditList(int $listId, int $userId): bool
    {
        $list = $this->repo->find($listId);
        if ($list === null) {
            return false;
        }

        return $list->getCreatedBy() === $userId;
    }

    public function canUserViewList(int $listId, int $userId): bool
    {
        $list = $this->repo->find($listId);
        if ($list === null) {
            return false;
        }

        return $list->isPublic() || $list->getCreatedBy() === $userId;
    }
}
