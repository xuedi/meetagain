<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Menu;
use App\Entity\MenuLocation;
use App\Form\MenuType;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminMenuController extends AbstractController
{
    public function __construct(
        private readonly MenuRepository $repo,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    #[Route('/admin/menu/', name: 'app_admin_menu')]
    public function cmsList(Request $request, Menu $menu): Response
    {
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
        }

        return $this->render('admin/menu/index.html.twig', [
            'active' => 'menu',
            'form' => $form,
            'items' => $this->repo->findAll(),
            'menu_locations' => MenuLocation::getTranslatedList($this->translator),
        ]);
    }
}
