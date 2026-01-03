<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/filmclub')]
class FrontpageController extends AbstractController
{
    public function __construct() {
    }

    #[Route('', name: 'app_plugin_filmclub_frontpage', methods: ['GET'])]
    public function pending(): Response
    {
        return $this->render('@Filmclub/frontpage.html.twig', [
            'films' => [],
        ]);
    }
}
