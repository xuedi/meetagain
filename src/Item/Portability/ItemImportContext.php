<?php declare(strict_types=1);

namespace App\Item\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Service\System\PortableImageImporter;

/**
 * The per-import services a contributor needs: turning an archive-relative image path back into a
 * persisted Image, and the account that owns everything an import creates.
 */
readonly class ItemImportContext
{
    public function __construct(
        private PortableImageImporter $imageImporter,
        private string $extractedArchiveDir,
        private User $systemUser,
    ) {}

    public function importImage(?string $archiveRelativePath, ImageType $type): ?Image
    {
        if ($archiveRelativePath === null || $archiveRelativePath === '') {
            return null;
        }

        return $this->imageImporter->import($this->extractedArchiveDir . '/' . $archiveRelativePath, $type, $this->systemUser);
    }

    public function getSystemUser(): User
    {
        return $this->systemUser;
    }
}
