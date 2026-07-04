<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\Media\ImageAttributionService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AttributionsController extends AbstractController
{
    public function __construct(
        private readonly ImageAttributionService $imageAttributionService,
    ) {}

    #[Route('/attributions', name: 'app_attributions')]
    public function index(): Response
    {
        return $this->render('attributions/index.html.twig', [
            'images' => $this->imageAttributionService->getVisibleAttributedImages(),
        ]);
    }
}
