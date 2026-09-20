<?php declare(strict_types=1);

namespace Plugin\Photos\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Item\ContributorInterface;
use App\Portability\Item\ImportResult;
use App\Portability\Item\UploadsInterface;
use App\Repository\UserRepository;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Entity\PhotoTranslation;
use Plugin\Photos\Repository\PhotoRepository;
use Plugin\Photos\Service\PhotoService;

readonly class PhotoContributor implements ContributorInterface, UploadsInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PhotoRepository $photoRepo,
        private ImageLocationService $imageLocationService,
        private UserRepository $userRepository,
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
    public function allItemIds(): array
    {
        return array_map(
            intval(...),
            $this->photoRepo
                ->createQueryBuilder('p')
                ->select('p.id')
                ->orderBy('p.id')
                ->getQuery()
                ->getSingleColumnResult(),
        );
    }

    #[Override]
    public function getUploaderIds(array $itemIds): array
    {
        $uploaders = [];
        foreach ($this->photoRepo->findBy(['id' => $itemIds]) as $photo) {
            $uploaders[(int) $photo->getId()] = $photo->getCreatedBy();
        }

        return $uploaders;
    }

    #[Override]
    public function exportItems(array $itemIds, ImageWriterInterface $images): array
    {
        $photos = $this->photoRepo->findBy(['id' => $itemIds]);
        $uploaderEmails = $this->uploaderEmails($photos);
        $rows = [];

        foreach ($photos as $photo) {
            $image = $photo->getImage();
            if (!$image instanceof Image) {
                continue;
            }

            $file = $images->addImage($image);
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
                'ref' => (int) $photo->getId(),
                'translations' => $translations,
                'meta' => $photo->getMeta(),
                'taken_at' => $photo->getTakenAt()?->format('Y-m-d H:i:s'),
                'image' => $file,
                'uploader_email' => $uploaderEmails[(int) $photo->getCreatedBy()] ?? null,
                'contest_submitted' => $photo->isContestSubmitted(),
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
            $uploader = $context->resolveRef(User::class, $row['uploader_email'] ?? null) ?? $context->getSystemUser();
            $image = $context->importImage($this->nullableString($row['image'] ?? null), ImageType::PluginPhotosPhoto, $uploader);
            if (!$image instanceof Image) {
                continue;
            }

            $photo = new Photo();
            $photo->setImage($image);
            $photo->setCreatedAt(new DateTimeImmutable());
            $photo->setCreatedBy((int) $uploader->getId());
            $photo->setMeta(is_array($row['meta'] ?? null) ? $row['meta'] : null);
            $photo->setTakenAt($this->dateTime($row['taken_at'] ?? null));
            $photo->setContestSubmitted((bool) ($row['contest_submitted'] ?? false));

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

        return new ImportResult(refToItemId: array_map(static fn(Photo $photo): int => (int) $photo->getId(), $refToPhoto), created: $created, matched: 0);
    }

    /**
     * @param list<Photo> $photos
     * @return array<int, string>
     */
    private function uploaderEmails(array $photos): array
    {
        $uploaderIds = array_values(array_unique(array_filter(array_map(static fn(Photo $photo): ?int => $photo->getCreatedBy(), $photos))));
        if ($uploaderIds === []) {
            return [];
        }

        $emails = [];
        foreach ($this->userRepository->findBy(['id' => $uploaderIds]) as $user) {
            $emails[(int) $user->getId()] = (string) $user->getEmail();
        }

        return $emails;
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
