<?php declare(strict_types=1);

namespace Tests\Unit\CheckGetRoutes\CleanOnly;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CleanController
{
    #[Route('/fixture/clean', name: 'fixture_clean', methods: ['GET'])]
    public function index(): Response
    {
        return new Response('ok');
    }
}
