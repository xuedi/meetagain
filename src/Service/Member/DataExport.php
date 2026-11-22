<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\Image;
use App\Entity\User;
use App\Service\Media\ImageService;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use ZipArchive;

readonly class DataExport
{
    /**
     * @param iterable<DataSectionInterface> $sections
     */
    public function __construct(
        #[AutowireIterator(DataSectionInterface::class)]
        private iterable $sections,
        private ImageService $imageService,
    ) {}

    public function build(User $user): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'member-data-');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot open the member data archive.');
        }

        foreach ($this->sections as $section) {
            foreach ($section->export($user) as $name => $rows) {
                $zip->addFromString(
                    $name . '.json',
                    (string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                );
            }
        }

        $avatar = $user->getImage();
        if ($avatar instanceof Image) {
            $content = $this->imageService->readOriginal($avatar);
            if ($content !== null) {
                $zip->addFromString('avatar/' . $avatar->getHash() . '.' . $avatar->getExtension(), $content);
            }
        }

        $zip->close();

        return $path;
    }
}
