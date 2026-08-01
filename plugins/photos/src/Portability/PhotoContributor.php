<?php declare(strict_types=1);

namespace Plugin\Photos\Portability;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Item\Portability\ContributorInterface;
use App\Item\Portability\ImportContext;
use App\Item\Portability\ImportResult;
use App\Item\Portability\PortableImageWriterInterface;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Entity\PhotoTranslation;
use Plugin\Photos\Repository\PhotoRepository;
use Plugin\Photos\Service\PhotoService;

readonly class PhotoContributor implements ContributorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PhotoRepository $photoRepo,
        private ImageLocationService $imageLocationService,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'photos';
    }

    #[Override]
    public function getItemType(): string
    {
        return PhotoService::ITEM_TYPE;
    }

    #[Override]
    public function exportItems(array $itemIds, PortableImageWriterInterface $images): array
    {
        $rows = [];

        foreach ($this->photoRepo->findBy(['id' => $itemIds]) as $photo) {
            $image = $photo->getImage();
            if (!$image instanceof Image) {
                continue;
            }

            $photoId = (int) $photo->getId();
            $file = $images->addImage($image, 'images/photos/' . $photoId . '/photo');
            if ($file === null) {
                continue;
            }

            $translations = [];
            foreach ($photo->getTranslations() as $translation) {
                $translations[(string) $translation->getLanguage()] = [
                    'title' => $translation->getTitle(),
                    'description' => $translation->getDescription(),
                ];
            }

            $rows[] = [
                'ref' => $photoId,
                'translations' => $translations,
                'meta' => $photo->getMeta(),
                'taken_at' => $photo->getTakenAt()?->format('Y-m-d H:i:s'),
                'image' => $file,
            ];
        }

        return $rows;
    }

    #[Override]
    public function importItems(array $rows, ImportContext $context): ImportResult
    {
        $refToPhoto = [];
        $created = 0;
        $imageLocations = [];

        foreach ($rows as $row) {
            $image = $context->importImage($this->nullableString($row['image'] ?? null), ImageType::PluginPhotosPhoto);
            if (!$image instanceof Image) {
                continue;
            }

            $photo = new Photo();
            $photo->setImage($image);
            $photo->setCreatedAt(new DateTimeImmutable());
            $photo->setCreatedBy((int) $context->getSystemUser()->getId());
            $photo->setMeta(is_array($row['meta'] ?? null) ? $row['meta'] : null);
            $photo->setTakenAt($this->dateTime($row['taken_at'] ?? null));

            foreach (is_array($row['translations'] ?? null) ? $row['translations'] : [] as $language => $fields) {
                $translation = new PhotoTranslation();
                $translation->setLanguage((string) $language);
                $translation->setTitle((string) ($fields['title'] ?? ''));
                $translation->setDescription($this->nullableString($fields['description'] ?? null));
                $photo->addTranslation($translation);
                $this->em->persist($translation);
            }

            $this->em->persist($photo);
            $refToPhoto[(int) ($row['ref'] ?? 0)] = $photo;
            $imageLocations[] = [$image, $photo];
            ++$created;
        }

        $this->em->flush();

        foreach ($imageLocations as [$image, $photo]) {
            $this->imageLocationService->addLocation((int) $image->getId(), ImageType::PluginPhotosPhoto, (int) $photo->getId());
        }

        return new ImportResult(
            refToItemId: array_map(static fn(Photo $photo): int => (int) $photo->getId(), $refToPhoto),
            created: $created,
            matched: 0,
        );
    }

    private function dateTime(mixed $value): ?DateTimeImmutable
    {
        $raw = $this->nullableString($value);
        if ($raw === null) {
            return null;
        }

        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw) ?: null;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
