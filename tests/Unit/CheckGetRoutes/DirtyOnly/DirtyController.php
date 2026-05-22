<?php declare(strict_types=1);

namespace Tests\Unit\CheckGetRoutes\DirtyOnly;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DirtyController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/fixture/dirty', name: 'fixture_dirty', methods: ['GET'])]
    public function index(): Response
    {
        $this->em->flush();

        return new Response('bad');
    }
}
