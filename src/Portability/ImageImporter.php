<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\ExtendedFilesystem;
use App\Service\Media\MetadataStripper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use finfo;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use RuntimeException;

readonly class ImageImporter
{
    private const string HASH_STEM = '/^[0-9a-f]{40}$/';

    public function __construct(
        private EntityManagerInterface $em,
        private ExtendedFilesystem $fs,
        private FilesystemOperator $imageStorage,
        private MetadataStripper $metadataStripper,
        private LoggerInterface $logger,
    ) {}

    public function import(
        string $imagePath,
        ImageType $type,
        User $uploader,
        ?string $attribution = null,
        bool $attributionNotRequired = false,
        bool $store = true,
    ): ?Image {
        if (!$this->fs->fileExists($imagePath)) {
            return null;
        }

        $content = (string) $this->fs->getFileContents($imagePath);
        $stem = strtolower(pathinfo($imagePath, PATHINFO_FILENAME));
        $hash = preg_match(self::HASH_STEM, $stem) === 1 ? $stem : sha1($content);

        $existing = $this->em->getRepository(Image::class)->findOneBy(['hash' => $hash]) ?? $this->findPendingImage($hash);
        if ($existing !== null) {
            return $existing;
        }

        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        if ($extension === '') {
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($content) ?: 'application/octet-stream';

        if ($store) {
            try {
                $content = $this->metadataStripper->strip($content);
            } catch (RuntimeException $e) {
                $this->logger->warning('Skipped an archived image that cannot be read: {path}: {message}', [
                    'path' => basename($imagePath),
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
            $key = $hash . '.' . $extension;
            if (!$this->imageStorage->fileExists($key)) {
                $this->imageStorage->write($key, $content);
            }
        }

        $image = new Image();
        $image->setHash($hash);
        $image->setExtension($extension);
        $image->setMimeType($mimeType);
        $image->setSize(strlen($content));
        $image->setType($type);
        $image->setUploader($uploader);
        $image->setAttribution($attribution);
        $image->setAttributionNotRequired($attributionNotRequired);
        $image->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($image);

        return $image;
    }

    private function findPendingImage(string $hash): ?Image
    {
        foreach ($this->em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Image && $entity->getHash() === $hash) {
                return $entity;
            }
        }

        return null;
    }
}
