<?php declare(strict_types=1);

namespace App\Twig;

use App\Controller\ImageUploadController;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RenderImageModalExtension extends AbstractExtension
{
    public function __construct(private readonly ImageUploadController $imageUploadController)
    {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('renderImageModal', $this->render(...)),
        ];
    }

    public function render(...$parameters): string
    {
        return $this->imageUploadController->imageReplaceModal((string)$parameters[0], $parameters[1])->getContent();
    }

    public function getName(): string
    {
        return 'renderImageModal';
    }
}
