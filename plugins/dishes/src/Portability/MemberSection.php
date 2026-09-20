<?php declare(strict_types=1);

namespace Plugin\Dishes\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Portability\DataCategory;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\PluginSectionInterface;
use App\Portability\Scope;
use App\Repository\UserRepository;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Dishes\Entity\Dish;
use Plugin\Dishes\Entity\DishImage;
use Plugin\Dishes\Entity\DishLike;
use Plugin\Dishes\Repository\DishLikeRepository;
use Plugin\Dishes\Repository\DishRepository;
use Plugin\Dishes\Service\DishService;

readonly class MemberSection implements PluginSectionInterface
{
    public const string KIND_LIKES = 'dishes_likes';
    public const string KIND_GALLERY = 'dishes_gallery';

    public function __construct(
        private EntityManagerInterface $em,
        private DishRepository $dishRepo,
        private DishLikeRepository $likeRepo,
        private UserRepository $userRepository,
        private ImageLocationService $imageLocationService,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'dishes';
    }

    #[Override]
    public function getOrder(): int
    {
        return 100;
    }

    #[Override]
    public function getPluginKey(): string
    {
        return 'dishes';
    }

    #[Override]
    public function getKindLabels(): array
    {
        return [
            self::KIND_LIKES => 'dishes_portability.kind_likes',
            self::KIND_GALLERY => 'dishes_portability.kind_gallery',
        ];
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        $dishIds = $scope->itemIds[DishService::ITEM_TYPE] ?? [];
        if ($dishIds === []) {
            return [];
        }

        $dishes = $this->dishRepo->findBy(['id' => $dishIds], ['id' => 'ASC']);
        $likes = $this->exportLikes($dishes, $scope);
        $gallery = $this->exportGallery($dishes, $scope, $images);
        if ($likes === [] && $gallery === []) {
            return [];
        }

        return ['likes' => $likes, 'gallery' => $gallery];
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        $locations = [];
        foreach ($this->rowsOf($rows, 'gallery') as $row) {
            $location = $this->importGalleryImage($row, $context);
            if ($location !== null) {
                $locations[] = $location;
            }
        }

        $liked = [];
        foreach ($this->rowsOf($rows, 'likes') as $row) {
            $this->importLike($row, $context, $liked);
        }

        if ($locations === []) {
            return;
        }

        $this->em->flush();
        foreach ($locations as [$image, $dish]) {
            $this->imageLocationService->addLocation((int) $image->getId(), ImageType::PluginDishesPreview, (int) $dish->getId());
        }
    }

    /**
     * @param list<Dish> $dishes
     * @return list<array{dish_ref: int, email: string}>
     */
    private function exportLikes(array $dishes, Scope $scope): array
    {
        if ($dishes === []) {
            return [];
        }

        $likes = $this->likeRepo->findBy(['dish' => $dishes], ['id' => 'ASC']);
        $userIds = array_values(array_unique(array_map(static fn(DishLike $like): int => (int) $like->getUserId(), $likes)));
        $users = [];
        if ($userIds !== []) {
            foreach ($this->userRepository->findBy(['id' => $userIds]) as $user) {
                $users[(int) $user->getId()] = $user;
            }
        }

        $rows = [];
        foreach ($likes as $like) {
            $user = $users[(int) $like->getUserId()] ?? null;
            if (!$user instanceof User || !$scope->grants($user, DataCategory::Interactions)) {
                continue;
            }

            $rows[] = ['dish_ref' => (int) $like->getDish()?->getId(), 'email' => (string) $user->getEmail()];
        }

        return $rows;
    }

    /**
     * @param list<Dish> $dishes
     * @return list<array<string, mixed>>
     */
    private function exportGallery(array $dishes, Scope $scope, ImageWriterInterface $images): array
    {
        $rows = [];
        foreach ($dishes as $dish) {
            $galleryImages = $dish->getGalleryImages()->toArray();
            usort($galleryImages, static fn(DishImage $a, DishImage $b): int => $a->getId() <=> $b->getId());

            foreach ($galleryImages as $galleryImage) {
                $image = $galleryImage->getImage();
                if (!$image instanceof Image) {
                    continue;
                }

                if (!$scope->carriesUpload($image->getUploader()?->getId())) {
                    continue;
                }

                $file = $images->addImage($image);
                if ($file === null) {
                    continue;
                }

                $rows[] = [
                    'dish_ref' => (int) $dish->getId(),
                    'image_file' => $file,
                    'uploader_email' => $image->getUploader()?->getEmail(),
                    'sort_order' => $galleryImage->getSortOrder(),
                    'created_at' => $galleryImage->getCreatedAt()?->format(DateTimeInterface::ATOM),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<array-key, mixed> $row
     * @return array{Image, Dish}|null
     */
    private function importGalleryImage(array $row, ImportContext $context): ?array
    {
        $dish = $this->resolveDish($row, $context, self::KIND_GALLERY);
        if (!$dish instanceof Dish) {
            return null;
        }

        $uploader = $context->resolveRef(User::class, $row['uploader_email'] ?? null);
        $image = $context->importImage($row['image_file'] ?? null, ImageType::PluginDishesPreview, $uploader);
        if (!$image instanceof Image) {
            $context->count(self::KIND_GALLERY, Outcome::Dropped);

            return null;
        }

        $isInGallery = array_any($dish->getGalleryImages()->toArray(), static fn(DishImage $existing): bool => $existing->getImage() === $image);
        if ($isInGallery) {
            $context->count(self::KIND_GALLERY, Outcome::Matched);

            return null;
        }

        $createdAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, (string) ($row['created_at'] ?? ''));
        $galleryImage = new DishImage()
            ->setDish($dish)
            ->setImage($image)
            ->setSortOrder((int) ($row['sort_order'] ?? 0))
            ->setCreatedAt($createdAt === false ? new DateTimeImmutable() : $createdAt);
        $dish->addGalleryImage($galleryImage);
        $this->em->persist($galleryImage);
        $context->count(self::KIND_GALLERY, Outcome::Created);

        return [$image, $dish];
    }

    /**
     * @param array<array-key, mixed> $row
     * @param array<string, true> $liked
     */
    private function importLike(array $row, ImportContext $context, array &$liked): void
    {
        $dish = $this->resolveDish($row, $context, self::KIND_LIKES);
        if (!$dish instanceof Dish) {
            return;
        }

        $user = $context->resolveRef(User::class, $row['email'] ?? null);
        if (!$user instanceof User) {
            $context->count(self::KIND_LIKES, Outcome::Dropped);

            return;
        }

        $userId = (int) $user->getId();
        $likeKey = $dish->getId() . '-' . $userId;
        if (isset($liked[$likeKey]) || $this->likeRepo->findByDishAndUser($dish, $userId) !== null) {
            $context->count(self::KIND_LIKES, Outcome::Matched);

            return;
        }

        $liked[$likeKey] = true;
        $this->em->persist(
            new DishLike()
                ->setDish($dish)
                ->setUserId($userId),
        );
        $dish->setLikes($dish->getLikes() + 1);
        $context->count(self::KIND_LIKES, Outcome::Created);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function resolveDish(array $row, ImportContext $context, string $kind): ?Dish
    {
        if (!$context->knowsItemType(DishService::ITEM_TYPE)) {
            $context->count($kind, Outcome::Skipped);

            return null;
        }

        $dishId = $context->resolveItem(DishService::ITEM_TYPE, $row['dish_ref'] ?? null);
        $dish = $dishId === null ? null : $this->dishRepo->find($dishId);
        if (!$dish instanceof Dish) {
            $context->count($kind, Outcome::Dropped);

            return null;
        }

        return $dish;
    }

    /**
     * @param array<array-key, mixed> $rows
     * @return list<array<array-key, mixed>>
     */
    private function rowsOf(array $rows, string $key): array
    {
        $block = is_array($rows[$key] ?? null) ? $rows[$key] : [];

        return array_values(array_filter($block, is_array(...)));
    }
}
