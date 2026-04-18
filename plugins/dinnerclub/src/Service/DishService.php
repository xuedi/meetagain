<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Service;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Entity\DishImage;
use Plugin\Dinnerclub\Entity\DishImageSuggestion;
use Plugin\Dinnerclub\Entity\DishLike;
use Plugin\Dinnerclub\Entity\DishSuggestion;
use Plugin\Dinnerclub\Entity\DishSuggestionField;
use Plugin\Dinnerclub\Entity\DishTranslation;
use Plugin\Dinnerclub\Enum\DishImageSuggestionType;
use Plugin\Dinnerclub\Repository\DishImageRepository;
use Plugin\Dinnerclub\Repository\DishImageSuggestionRepository;
use Plugin\Dinnerclub\Repository\DishLikeRepository;
use Plugin\Dinnerclub\Repository\DishRepository;
use RuntimeException;

readonly class DishService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DishRepository $repo,
        private DishLikeRepository $likeRepo,
        private DishImageRepository $imageRepo,
        private DishImageSuggestionRepository $imageSuggestionRepo,
        private ImageLocationService $imageLocationService,
    ) {}

    public function createDish(
        string $name,
        string $language,
        int $userId,
        bool $isManager,
        ?string $phonetic = null,
        ?string $description = null,
        ?string $recipe = null,
        ?string $origin = null,
    ): Dish {
        $dish = new Dish();
        $dish->setCreatedBy($userId);
        $dish->setCreatedAt(new DateTimeImmutable());
        $dish->setApproved($isManager);
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

        return $dish;
    }

    public function getDish(int $id): ?Dish
    {
        return $this->repo->find($id);
    }

    public function getApprovedDishes(): array
    {
        return $this->repo->findApproved();
    }

    public function getAllDishes(): array
    {
        return $this->repo->findBy([], ['createdAt' => 'DESC']);
    }

    public function getPendingDishes(): array
    {
        return $this->repo->findPending();
    }

    public function getDishesWithSuggestions(): array
    {
        return $this->repo->findWithSuggestions();
    }

    public function approveDish(int $id): void
    {
        $dish = $this->repo->find($id);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        $dish->setApproved(true);
        $this->em->persist($dish);
        $this->em->flush();
    }

    public function rejectDish(int $id): void
    {
        $dish = $this->repo->find($id);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        if ($dish->getApproved()) {
            throw new RuntimeException('Cannot reject approved dish');
        }

        $this->em->remove($dish);
        $this->em->flush();
    }

    public function addSuggestion(
        int $dishId,
        int $userId,
        DishSuggestionField $field,
        string $language,
        string $value,
    ): void {
        $dish = $this->repo->find($dishId);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        $suggestion = DishSuggestion::create(createdBy: $userId, field: $field, language: $language, value: $value);

        $dish->addSuggestion($suggestion);
        $this->em->persist($dish);
        $this->em->flush();
    }

    public function applySuggestion(int $dishId, string $hash): int
    {
        $dish = $this->repo->find($dishId);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        $suggestion = $dish->findSuggestionByHash($hash);
        if ($suggestion === null) {
            throw new RuntimeException('Suggestion not found');
        }

        if ($suggestion->field === DishSuggestionField::Origin) {
            $dish->setOrigin($suggestion->value);
        }
        if ($suggestion->field !== DishSuggestionField::Origin) {
            $translation = $dish->findTranslation($suggestion->language);
            if ($translation === null) {
                $translation = new DishTranslation();
                $translation->setLanguage($suggestion->language);
                $translation->setName('');
                $translation->setDescription('');
                $dish->addTranslation($translation);
            }

            /** @var DishSuggestionField::Name|DishSuggestionField::Phonetic|DishSuggestionField::Description|DishSuggestionField::Recipe $field */
            $field = $suggestion->field;
            match ($field) {
                DishSuggestionField::Name => $translation->setName($suggestion->value),
                DishSuggestionField::Phonetic => $dish->setPhonetic($suggestion->value),
                DishSuggestionField::Description => $translation->setDescription($suggestion->value),
                DishSuggestionField::Recipe => $translation->setRecipe($suggestion->value),
            };
        }

        $dish->removeSuggestionByHash($hash);
        $this->em->persist($dish);
        $this->em->flush();

        return count($dish->getSuggestionObjects());
    }

    public function denySuggestion(int $dishId, string $hash): int
    {
        $dish = $this->repo->find($dishId);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        $dish->removeSuggestionByHash($hash);
        $this->em->persist($dish);
        $this->em->flush();

        return count($dish->getSuggestionObjects());
    }

    public function toggleLike(int $dishId, int $userId): bool
    {
        $dish = $this->repo->find($dishId);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        $existing = $this->likeRepo->findByDishAndUser($dish, $userId);
        if ($existing !== null) {
            $this->em->remove($existing);
            $this->em->flush();

            return false;
        }

        $like = new DishLike();
        $like->setDish($dish);
        $like->setUserId($userId);
        $this->em->persist($like);
        $this->em->flush();

        return true;
    }

    /** @return int[] */
    public function getLikedDishIds(int $userId): array
    {
        return $this->likeRepo->findDishIdsByUser($userId);
    }

    public function isLikedByUser(int $dishId, int $userId): bool
    {
        $dish = $this->repo->find($dishId);
        if ($dish === null) {
            return false;
        }

        return $this->likeRepo->findByDishAndUser($dish, $userId) !== null;
    }

    public function updateTranslation(
        int $dishId,
        string $language,
        int $userId,
        bool $isManager,
        string $name,
        ?string $description,
        ?string $recipe,
    ): void {
        $dish = $this->repo->find($dishId);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        $translation = $dish->findTranslation($language);
        $isNewTranslation = $translation === null;

        if ($isManager) {
            if ($isNewTranslation) {
                $translation = new DishTranslation();
                $translation->setLanguage($language);
                $dish->addTranslation($translation);
            }

            $translation->setName($name);
            $translation->setDescription($description ?? '');
            $translation->setRecipe($recipe);

            $this->em->persist($dish);
            $this->em->flush();

            return;
        }

        if ($isNewTranslation || $translation->getName() !== $name) {
            $dish->addSuggestion(DishSuggestion::create(
                createdBy: $userId,
                field: DishSuggestionField::Name,
                language: $language,
                value: $name,
            ));
        }

        $currentDescription = $isNewTranslation ? '' : $translation->getDescription();
        if ($currentDescription !== ($description ?? '')) {
            $dish->addSuggestion(DishSuggestion::create(
                createdBy: $userId,
                field: DishSuggestionField::Description,
                language: $language,
                value: $description ?? '',
            ));
        }

        $currentRecipe = $isNewTranslation ? null : $translation->getRecipe();
        if ($currentRecipe !== $recipe && ($recipe !== null && $recipe !== '')) {
            $dish->addSuggestion(DishSuggestion::create(
                createdBy: $userId,
                field: DishSuggestionField::Recipe,
                language: $language,
                value: $recipe,
            ));
        }

        $this->em->persist($dish);
        $this->em->flush();
    }

    public function updateOrigin(int $dishId, int $userId, bool $isManager, ?string $origin): void
    {
        $dish = $this->repo->find($dishId);
        if ($dish === null) {
            throw new RuntimeException('Dish not found');
        }

        if ($isManager) {
            $dish->setOrigin($origin);
            $this->em->persist($dish);
            $this->em->flush();

            return;
        }

        if ($dish->getOrigin() !== $origin && $origin !== null && $origin !== '') {
            $dish->addSuggestion(DishSuggestion::create(
                createdBy: $userId,
                field: DishSuggestionField::Origin,
                language: '',
                value: $origin,
            ));

            $this->em->persist($dish);
            $this->em->flush();
        }
    }

    public function saveBaseData(Dish $dish): void
    {
        $this->em->persist($dish);
        $this->em->flush();
    }

    public function detach(Dish $dish): void
    {
        $this->em->detach($dish);
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

        // The unique constraint deduplicates when the same image is both gallery and preview
        $this->imageLocationService->addLocation($image->getId(), ImageType::PluginDish, $dish->getId());

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
            $this->imageLocationService->removeLocation($image->getId(), ImageType::PluginDish, $dish->getId());
        }
    }

    public function addImageSuggestion(Dish $dish, Image $image, DishImageSuggestionType $type, int $userId): DishImageSuggestion
    {
        $suggestion = new DishImageSuggestion();
        $suggestion->setDish($dish);
        $suggestion->setImage($image);
        $suggestion->setType($type);
        $suggestion->setSuggestedBy($userId);
        $suggestion->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($suggestion);
        $this->em->flush();

        return $suggestion;
    }

    public function applyImageSuggestion(int $suggestionId): int
    {
        $suggestion = $this->imageSuggestionRepo->find($suggestionId);
        if ($suggestion === null) {
            throw new RuntimeException('Image suggestion not found');
        }

        $dish = $suggestion->getDish();
        $image = $suggestion->getImage();

        if ($dish === null || $image === null) {
            throw new RuntimeException('Invalid image suggestion state');
        }

        $oldPreviewId = null;

        if ($suggestion->getType() === DishImageSuggestionType::AddImage) {
            $this->addGalleryImage($dish, $image);
        }
        if ($suggestion->getType() !== DishImageSuggestionType::AddImage) {
            $oldPreviewId = $dish->getPreviewImage()?->getId();
            $dish->setPreviewImage($image);
            $this->em->persist($dish);
        }

        $this->em->remove($suggestion);
        $this->em->flush();

        if ($suggestion->getType() !== DishImageSuggestionType::AddImage) {
            if ($oldPreviewId !== null) {
                $this->imageLocationService->removeLocation($oldPreviewId, ImageType::PluginDish, $dish->getId());
            }
            $this->imageLocationService->addLocation($image->getId(), ImageType::PluginDish, $dish->getId());
        }

        return $this->imageSuggestionRepo->countByDish($dish);
    }

    public function denyImageSuggestion(int $suggestionId): int
    {
        $suggestion = $this->imageSuggestionRepo->find($suggestionId);
        if ($suggestion === null) {
            throw new RuntimeException('Image suggestion not found');
        }

        $dish = $suggestion->getDish();
        if ($dish === null) {
            throw new RuntimeException('Invalid image suggestion state');
        }

        $this->em->remove($suggestion);
        $this->em->flush();

        return $this->imageSuggestionRepo->countByDish($dish);
    }

    public function getImageSuggestionsForDish(Dish $dish): array
    {
        return $this->imageSuggestionRepo->findByDish($dish);
    }

    public function getDishesWithImageSuggestions(): array
    {
        return $this->imageSuggestionRepo->findDishesWithPendingSuggestions();
    }

    public function countImageSuggestions(Dish $dish): int
    {
        return $this->imageSuggestionRepo->countByDish($dish);
    }
}
