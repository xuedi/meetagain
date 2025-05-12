<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use App\Controller\AbstractController;
use App\Repository\TranslationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    #[Route('/api/', name: 'app_api')]
    public function index(): Response
    {
        return $this->render('_non_locale/api.html.twig');
    }
    #[Route('/api/translations', name: 'app_api_translations', methods: ['GET'])]
    public function translationsIndex(TranslationRepository $repo): Response
    {
        return new JsonResponse($repo->getExportList());
    }
    #[Route('/api/glossary', name: 'app_api_glossary', methods: ['GET'])]
    public function glossaryIndex(): Response
    {
        return new JsonResponse('glossary');
    }
}
