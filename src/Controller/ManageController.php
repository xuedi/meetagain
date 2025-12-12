<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\TranslationSuggestionStatus;
use App\Repository\TranslationRepository;
use App\Repository\TranslationSuggestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ManageController extends AbstractController
{
    public const string ROUTE_MANAGE = 'app_manage';

    public function __construct(
        private readonly TranslationSuggestionRepository $translationSuggestionRepo,
    ) {
    }

    #[Route('/manage', name: self::ROUTE_MANAGE)]
    public function index(): Response
    {
        return $this->render('manage/index.html.twig', [
            'translationSuggestions' => $this->translationSuggestionRepo->findBy(
                ['status' => TranslationSuggestionStatus::Requested],
                ['createdAt' => 'DESC'],
            ),
        ]);
    }
}
