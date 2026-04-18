<?php declare(strict_types=1);

namespace Plugin\Bookclub\Controller;

use App\Controller\AbstractController;
use Plugin\Bookclub\Service\BookService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bookclub/manage')]
#[IsGranted('ROLE_ORGANIZER')]
final class ApprovalController extends AbstractController
{
    public function __construct(
        private readonly BookService $bookService,
    ) {}

    #[Route('/pending', name: 'app_plugin_bookclub_pending', methods: ['GET'])]
    public function pending(): Response
    {
        return $this->render('@Bookclub/manage/pending.html.twig', [
            'books' => $this->bookService->getPendingList(),
        ]);
    }
}
