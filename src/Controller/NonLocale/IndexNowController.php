<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexNowController extends AbstractController
{
    #[Route('/6fdbe8408abb4a2687e50d51ecee70e8.txt', name: 'app_indexnow')]
    public function index(): Response
    {
        return new Response(
            '6fdbe8408abb4a2687e50d51ecee70e8',
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain'],
        );
    }
}
