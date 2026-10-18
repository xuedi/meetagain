<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\Image;
use League\Flysystem\FilesystemOperator;
use Override;
use ZipArchive;

class ZipImageWriter implements ImageWriterInterface
{
    /** @var array<string, true> */
    private array $written = [];

    /** @var array<string, array{attribution: string|null, attribution_not_required: bool}> */
    private array $attributions = [];

    /** @var list<string> */
    private array $copies = [];

    public function __construct(
        private readonly ?ZipArchive $zip,
        private readonly FilesystemOperator $imageStorage,
    ) {}

    #[Override]
    public function addImage(Image $image): ?string
    {
        $fileName = $image->getHash() . '.' . $image->getExtension();
        $zipPath = 'images/' . $fileName;
        if (isset($this->written[$zipPath])) {
            return $zipPath;
        }

        if (!$this->imageStorage->fileExists($fileName)) {
            return null;
        }

        if ($this->zip !== null) {
            $copy = (string) tempnam(sys_get_temp_dir(), 'meetagain-export-image-');
            file_put_contents($copy, $this->imageStorage->readStream($fileName));
            $this->copies[] = $copy;
            $this->zip->addFile($copy, $zipPath);
        }
        $this->written[$zipPath] = true;

        $hasAttribution = $image->getAttribution() !== null && $image->getAttribution() !== '';
        if ($hasAttribution || $image->isAttributionNotRequired()) {
            $this->attributions[$zipPath] = [
                'attribution' => $image->getAttribution(),
                'attribution_not_required' => $image->isAttributionNotRequired(),
            ];
        }

        return $zipPath;
    }

    /**
     * @return array<string, array{attribution: string|null, attribution_not_required: bool}>
     */
    public function getAttributions(): array
    {
        $attributions = $this->attributions;
        ksort($attributions);

        return $attributions;
    }

    public function removeCopies(): void
    {
        foreach ($this->copies as $copy) {
            if (is_file($copy)) {
                unlink($copy);
            }
        }
        $this->copies = [];
    }
}
