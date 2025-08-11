<?php declare(strict_types=1);

namespace Plugin\Dishes\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/dishes')]
class IndexController extends AbstractController
{
    #[Route('', name: 'app_plugin_dishes', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@Dishes/index.html.twig', []);
    }
}
