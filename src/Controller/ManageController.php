<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ManageController extends AbstractController
{
    public const string ROUTE_MANAGE = 'app_manage';

    #[Route('/manage', name: self::ROUTE_MANAGE)]
    public function index(): Response
    {
        // Placeholder for future functionality
        // Translation suggestions moved to admin dashboard
        return $this->redirectToRoute('app_default');
    }
}
