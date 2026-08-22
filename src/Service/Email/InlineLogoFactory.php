<?php declare(strict_types=1);

namespace App\Service\Email;

use App\Repository\ImageRepository;
use App\Service\Media\ImageService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Part\DataPart;

class InlineLogoFactory
{
    public const string CID_NAME = 'site-logo';

    private const int HEIGHT = 120;
    private const string FALLBACK_ASSET = '/assets/images/logo.webp';

    /** @var array<string, string|false> */
    private array $memo = [];

    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly ImageService $imageService,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function create(?int $imageId): ?DataPart
    {
        $key = (string) ($imageId ?? 0);
        if (!array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $this->render($imageId) ?? false;
        }

        $png = $this->memo[$key];
        if ($png === false) {
            return null;
        }

        return new DataPart($png, self::CID_NAME, 'image/png')->asInline();
    }

    private function render(?int $imageId): ?string
    {
        $source = $this->projectDir . self::FALLBACK_ASSET;
        if ($imageId !== null) {
            $image = $this->imageRepository->find($imageId);
            if ($image === null) {
                return null;
            }

            $source = $this->imageService->getSourcePath($image);
        }

        return $this->imageService->renderPng($source, self::HEIGHT);
    }
}
