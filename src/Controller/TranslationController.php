<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\TranslationService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/translation')]
class TranslationController extends AbstractController
{
    public const string ROUTE_MANAGE = 'app_translation';

    #[Route('', name: self::ROUTE_MANAGE)]
    public function index(TranslationService $translationService): Response
    {
        dump('dfhghg');
        return $this->render('translation/index.html.twig', [
            'translationMatrix' => $translationService->getMatrix(),
        ]);
    }

    #[Route('/edit', name: 'app_translation_edit')]
    public function edit(TranslationService $translationService): Response
    {
        return $this->render('translation/edit.html.twig');
    }
}
