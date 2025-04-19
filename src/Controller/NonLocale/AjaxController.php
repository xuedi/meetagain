<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ajax')]
class AjaxController extends AbstractController
{
    #[Route('/', name: 'app_ajax', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('_non_locale/ajax.html.twig');
    }
}
