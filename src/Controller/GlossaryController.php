<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GlossaryController extends AbstractController
{
    #[Route('/glossary/', name: 'app_glossary')]
    public function index(): Response
    {
        return $this->render('glossary/index.html.twig');
    }
}
