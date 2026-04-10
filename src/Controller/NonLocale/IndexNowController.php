<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexNowController extends AbstractController
{
    #[Route('/{key}.txt', name: 'app_indexnow', requirements: ['key' => '[a-zA-Z0-9\-]{8,128}'])]
    public function index(string $key): Response
    {
        return new Response(
            $key,
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain'],
        );
    }
}
