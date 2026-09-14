<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\Image;
use Override;
use ZipArchive;

class ZipImageWriter implements ImageWriterInterface
{
    /** @var array<string, true> */
    private array $written = [];

    /** @var array<string, array{attribution: string|null, attribution_not_required: bool}> */
    private array $attributions = [];

    public function __construct(
        private readonly ?ZipArchive $zip,
        private readonly string $projectDir,
    ) {}

    #[Override]
    public function addImage(Image $image): ?string
    {
        $fileName = $image->getHash() . '.' . $image->getExtension();
        $zipPath = 'images/' . $fileName;
        if (isset($this->written[$zipPath])) {
            return $zipPath;
        }

        $sourcePath = $this->projectDir . '/data/images/' . $fileName;
        if (!file_exists($sourcePath)) {
            return null;
        }

        $this->zip?->addFile($sourcePath, $zipPath);
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
}
