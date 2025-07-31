<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use App\Repository\TranslationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/', name: 'app_api')]
    public function index(): Response
    {
        return $this->render('_non_locale/api.html.twig');
    }

    #[Route('/translations', name: 'app_api_translations', methods: ['GET'])]
    public function translationsIndex(TranslationRepository $repo): Response
    {
        return new JsonResponse($repo->getExportList());
    }

    #[Route('/glossary', name: 'app_api_glossary', methods: ['GET'])]
    public function glossaryIndex(): Response
    {
        return new JsonResponse('glossary');
    }

    #[Route('/status', name: 'app_api_status', methods: ['GET'])]
    public function statusIndex(): Response
    {
        return new JsonResponse('OK');
    }
}
