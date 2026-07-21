<?php declare(strict_types=1);

namespace Plugin\Dishes\Portability;

use App\Entity\Image;
use App\Entity\PronunciationSystem;
use App\Enum\ImageType;
use App\Item\Portability\ItemImportContext;
use App\Item\Portability\ItemImportResult;
use App\Item\Portability\ItemPortabilityContributorInterface;
use App\Item\Portability\PortableImageWriterInterface;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Dishes\Entity\Dish;
use Plugin\Dishes\Entity\DishImage;
use Plugin\Dishes\Entity\DishTranslation;
use Plugin\Dishes\Repository\DishRepository;
use Plugin\Dishes\Service\DishService;

/**
 * Dishes have no natural key, so every imported row creates a new dish - the same rule events
 * follow. The pronunciation system travels as its (language, name) pair because its ids are
 * instance-local.
 */
readonly class DishPortabilityContributor implements ItemPortabilityContributorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private DishRepository $dishRepo,
        private ImageLocationService $imageLocationService,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'dishes';
    }

    #[Override]
    public function getItemType(): string
    {
        return DishService::ITEM_TYPE;
    }

    #[Override]
    public function exportItems(array $itemIds, PortableImageWriterInterface $images): array
    {
        $rows = [];

        foreach ($this->dishRepo->findBy(['id' => $itemIds]) as $dish) {
            $dishId = (int) $dish->getId();

            $translations = [];
            foreach ($dish->getTranslations() as $translation) {
                $translations[(string) $translation->getLanguage()] = [
                    'name' => $translation->getName(),
                    'description' => $translation->getDescription(),
                    'recipe' => $translation->getRecipe(),
                ];
            }

            $gallery = [];
            $index = 0;
            foreach ($dish->getGalleryImages() as $galleryImage) {
                $image = $galleryImage->getImage();
                if (!$image instanceof Image) {
                    continue;
                }

                $file = $images->addImage($image, 'images/dishes/' . $dishId . '/gallery-' . $index);
                ++$index;
                if ($file === null) {
                    continue;
                }

                $gallery[] = ['file' => $file, 'sort_order' => $galleryImage->getSortOrder()];
            }

            $pronunciation = $dish->getPronunciationSystem();

            $rows[] = [
                'ref' => $dishId,
                'translations' => $translations,
                'phonetic' => $dish->getPhonetic(),
                'origin' => $dish->getOrigin(),
                'pronunciation' => $pronunciation instanceof PronunciationSystem
                    ? ['language' => $pronunciation->getLanguage(), 'name' => $pronunciation->getName()]
                    : null,
                'preview_image' => $dish->getPreviewImage() instanceof Image
                    ? $images->addImage($dish->getPreviewImage(), 'images/dishes/' . $dishId . '/preview')
                    : null,
                'gallery' => $gallery,
            ];
        }

        return $rows;
    }

    #[Override]
    public function importItems(array $rows, ItemImportContext $context): ItemImportResult
    {
        $refToItemId = [];
        $created = 0;
        $imageLocations = [];

        foreach ($rows as $row) {
            $dish = new Dish();
            $dish->setCreatedAt(new DateTimeImmutable());
            $dish->setCreatedBy((int) $context->getSystemUser()->getId());
            $dish->setPhonetic($this->nullableString($row['phonetic'] ?? null));
            $dish->setOrigin($this->nullableString($row['origin'] ?? null));
            $dish->setPronunciationSystem($this->findPronunciationSystem($row['pronunciation'] ?? null));

            foreach (is_array($row['translations'] ?? null) ? $row['translations'] : [] as $language => $fields) {
                $translation = new DishTranslation();
                $translation->setLanguage((string) $language);
                $translation->setName((string) ($fields['name'] ?? ''));
                $translation->setDescription((string) ($fields['description'] ?? ''));
                $translation->setRecipe($this->nullableString($fields['recipe'] ?? null));
                $dish->addTranslation($translation);
                $this->em->persist($translation);
            }

            $preview = $context->importImage($this->nullableString($row['preview_image'] ?? null), ImageType::PluginDishesPreview);
            if ($preview instanceof Image) {
                $dish->setPreviewImage($preview);
                $imageLocations[] = [$preview, $dish];
            }

            foreach (is_array($row['gallery'] ?? null) ? $row['gallery'] : [] as $galleryData) {
                $image = $context->importImage($this->nullableString($galleryData['file'] ?? null), ImageType::PluginDishesPreview);
                if (!$image instanceof Image) {
                    continue;
                }

                $galleryImage = new DishImage();
                $galleryImage->setDish($dish);
                $galleryImage->setImage($image);
                $galleryImage->setSortOrder((int) ($galleryData['sort_order'] ?? 0));
                $galleryImage->setCreatedAt(new DateTimeImmutable());
                $this->em->persist($galleryImage);
                $imageLocations[] = [$image, $dish];
            }

            $this->em->persist($dish);
            $refToItemId[(int) ($row['ref'] ?? 0)] = $dish;
            ++$created;
        }

        $this->em->flush();

        foreach ($imageLocations as [$image, $dish]) {
            $this->imageLocationService->addLocation((int) $image->getId(), ImageType::PluginDishesPreview, (int) $dish->getId());
        }

        return new ItemImportResult(
            refToItemId: array_map(static fn(Dish $dish): int => (int) $dish->getId(), $refToItemId),
            created: $created,
            matched: 0,
        );
    }

    private function findPronunciationSystem(mixed $pronunciation): ?PronunciationSystem
    {
        if (!is_array($pronunciation)) {
            return null;
        }

        return $this->em->getRepository(PronunciationSystem::class)->findOneBy([
            'language' => (string) ($pronunciation['language'] ?? ''),
            'name' => (string) ($pronunciation['name'] ?? ''),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
