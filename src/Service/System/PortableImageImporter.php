<?php declare(strict_types=1);

namespace App\Service\System;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\ExtendedFilesystem;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use finfo;

/**
 * Turns a file extracted from an import archive into a persisted Image, deduplicating on the SHA1
 * of its content so the same picture referenced from several rows lands once. Thumbnails stay
 * ungenerated until first requested.
 */
readonly class PortableImageImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private ExtendedFilesystem $fs,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    public function import(string $imagePath, ImageType $type, User $uploader): ?Image
    {
        if (!$this->fs->fileExists($imagePath)) {
            return null;
        }

        $content = (string) $this->fs->getFileContents($imagePath);
        $hash = sha1($content);

        $existing = $this->em->getRepository(Image::class)->findOneBy(['hash' => $hash]);
        if ($existing !== null) {
            return $existing;
        }

        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        if ($extension === '') {
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($content) ?: 'application/octet-stream';

        $targetDir = $this->projectDir . '/data/images/';
        if (!$this->fs->isDirectory($targetDir)) {
            $this->fs->makeDirectory($targetDir);
        }

        $this->fs->putFileContents($targetDir . $hash . '.' . $extension, $content);

        $image = new Image();
        $image->setHash($hash);
        $image->setExtension($extension);
        $image->setMimeType($mimeType);
        $image->setSize(strlen($content));
        $image->setType($type);
        $image->setUploader($uploader);
        $image->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($image);

        return $image;
    }
}
