<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Repository\ImageRepository;
use Twig\Environment;

readonly class ImageHtmlRenderer
{
    public function __construct(
        private ImageRepository $imageRepo,
        private Environment $twig,
    ) {}

    public function renderThumbnail(int $id, string $size = '50x50'): string
    {
        $image = $this->imageRepo->findOneBy(['id' => $id]);

        return $this->twig->render('_components/image.html.twig', [
            'image' => $image,
            'size' => $size,
        ]);
    }
}
