<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Menu;
use App\Entity\MenuLocation;
use App\Entity\MenuType as EnumMenuType;
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

    #[Route('/admin/menu/{edit}', name: 'app_admin_menu')]
    public function menuList(Request $request, Menu $menu, int $edit = null): Response
    {
        if ($edit !== null) {
            $menu = $this->repo->find($edit);
        }
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
        }

        return $this->render('admin/menu/index.html.twig', [
            'active' => 'menu',
            'form' => $form,
            'edit' => $edit,
            'menuTypeList' => EnumMenuType::getTranslatedList($this->translator),
            'menuTypeActive' => $menu?->getType()?->value ?? 0,
            'items' => $this->repo->getAllSlugified(),
            'menu_locations' => MenuLocation::getTranslatedList($this->translator),
        ]);
    }

    #[Route('/admin/menu/{id}/up', name: 'app_admin_menu_up')]
    public function menuUp(int $id): Response
    {
        return $this->forward('app_admin_menu');
    }

    #[Route('/admin/menu/{id}/down', name: 'app_admin_menu_down')]
    public function menuDown(int $id): Response
    {
        return $this->forward('app_admin_menu');
    }
}
