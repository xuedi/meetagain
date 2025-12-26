<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\CmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractController
{
    public function __construct(private readonly CmsService $cms)
    {
    }
    #[Route('/', name: 'app_default')]
    public function index(Request $request): Response
    {
        return $this->cms->handle($request->getLocale(), 'index', $this->getResponse());
    }

    #[Route('/language/{locale}', name: 'app_default_language')]
    public function setLanguage(Request $request, EntityManagerInterface $entityManager, string $locale): Response
    {
        // set session
        $session = $request->getSession();
        $session->set('_locale', $locale);

        // set user preferences in DB
        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setLocale($locale);
            $entityManager->persist($user);
            $entityManager->flush();
        }

        return $this->forward('App\Controller\IndexController::index'); // TODO: add proper route instead
    }
}
