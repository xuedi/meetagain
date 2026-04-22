<?php declare(strict_types=1);

namespace App\Controller\NonLocale;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ErrorTestController extends AbstractController
{
    #[Route('/9bdf52a4830779a1383ac24f1b3ed054', name: 'app_exception_test')]
    public function index(): Response
    {
        throw new Exception('Test exception');
    }
}
