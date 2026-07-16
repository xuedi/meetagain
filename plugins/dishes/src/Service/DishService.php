<?php declare(strict_types=1);

namespace Plugin\Dishes\Service;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Enum\ItemAction;
use App\Item\ItemActionDispatcher;
use App\Item\ItemFilterService;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Dishes\Entity\Dish;
use Plugin\Dishes\Entity\DishImage;
use Plugin\Dishes\Entity\DishLike;
use Plugin\Dishes\Entity\DishTranslation;
use Plugin\Dishes\Repository\DishImageRepository;
use Plugin\Dishes\Repository\DishLikeRepository;
use Plugin\Dishes\Repository\DishRepository;
use RuntimeException;

readonly class DishService
{
    public const string ITEM_TYPE = 'dish';

    public function __construct(
        private EntityManagerInterface $em,
        private DishRepository $dishRepo,
        private DishLikeRepository $likeRepo,
        private DishImageRepository $imageRepo,
        private ItemFilterService $itemFilter,
        private ItemActionDispatcher $dispatcher,
        private ImageLocationService $imageLocationService,
    ) {}

    public function create(string $name, string $language, ?string $description, ?string $recipe, ?string $phonetic, ?string $origin, int $userId): Dish
    {
        $dish = new Dish();
        $dish->setCreatedBy($userId);
        $dish->setCreatedAt(new DateTimeImmutable());
        $dish->setPhonetic($phonetic);
        $dish->setOrigin($origin);

        $translation = new DishTranslation();
        $translation->setLanguage($language);
        $translation->setName($name);
        $translation->setDescription($description ?? '');
        $translation->setRecipe($recipe);
        $dish->addTranslation($translation);

        $this->em->persist($dish);
        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Created, self::ITEM_TYPE, (int) $dish->getId());

        return $dish;
    }

    public function updateTranslation(Dish $dish, string $language, string $name, ?string $description, ?string $recipe): void
    {
        $translation = $dish->findTranslation($language);
        if ($translation === null) {
            $translation = new DishTranslation();
            $translation->setLanguage($language);
            $dish->addTranslation($translation);
        }

        $translation->setName($name);
        $translation->setDescription($description ?? '');
        $translation->setRecipe($recipe);

        $this->em->persist($dish);
        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Updated, self::ITEM_TYPE, (int) $dish->getId());
    }

    public function saveBaseData(Dish $dish): void
    {
        $this->em->persist($dish);
        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Updated, self::ITEM_TYPE, (int) $dish->getId());
    }

    public function delete(Dish $dish): void
    {
        $dishId = (int) $dish->getId();

        foreach ($dish->getGalleryImages() as $galleryImage) {
            $image = $galleryImage->getImage();
            if ($image !== null) {
                $this->imageLocationService->removeLocation((int) $image->getId(), ImageType::PluginDishesPreview, $dishId);
            }
        }

        $this->em->remove($dish);
        $this->em->flush();

        $this->dispatcher->dispatch(ItemAction::Deleted, self::ITEM_TYPE, $dishId);
    }

    public function addGalleryImage(Dish $dish, Image $image): DishImage
    {
        $dishImage = new DishImage();
        $dishImage->setDish($dish);
        $dishImage->setImage($image);
        $dishImage->setCreatedAt(new DateTimeImmutable());
        $this->em->persist($dishImage);

        if ($dish->getPreviewImage() === null) {
            $dish->setPreviewImage($image);
            $this->em->persist($dish);
        }

        $this->em->flush();

        // The unique constraint deduplicates when the same image is both gallery and preview.
        $this->imageLocationService->addLocation((int) $image->getId(), ImageType::PluginDishesPreview, (int) $dish->getId());

        return $dishImage;
    }

    public function removeGalleryImage(int $dishImageId): void
    {
        $dishImage = $this->imageRepo->find($dishImageId);
        if ($dishImage === null) {
            return;
        }

        $dish = $dishImage->getDish();
        $image = $dishImage->getImage();

        $this->em->remove($dishImage);

        if ($dish !== null && $image !== null && $dish->getPreviewImage()?->getId() === $image->getId()) {
            $dish->setPreviewImage(null);
            $this->em->persist($dish);
        }

        $this->em->flush();

        if ($dish !== null && $image !== null) {
            $this->imageLocationService->removeLocation((int) $image->getId(), ImageType::PluginDishesPreview, (int) $dish->getId());
        }
    }

    public function toggleLike(Dish $dish, int $userId): bool
    {
        $existing = $this->likeRepo->findByDishAndUser($dish, $userId);
        if ($existing !== null) {
            $this->em->remove($existing);
            $dish->setLikes($dish->getLikes() - 1);
            $this->em->flush();

            return false;
        }

        $like = new DishLike();
        $like->setDish($dish);
        $like->setUserId($userId);
        $this->em->persist($like);
        $dish->setLikes($dish->getLikes() + 1);
        $this->em->flush();

        return true;
    }

    public function isLikedByUser(Dish $dish, int $userId): bool
    {
        return $this->likeRepo->findByDishAndUser($dish, $userId) !== null;
    }

    /** @return Dish[] */
    public function getList(): array
    {
        return $this->dishRepo->findAll($this->itemFilter->getAllowedItemIds(self::ITEM_TYPE));
    }

    public function get(int $id): ?Dish
    {
        return $this->dishRepo->find($id);
    }
}
