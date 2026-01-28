<?php declare(strict_types=1);

namespace App\AdminModules\Cms;

use App\Entity\Menu;
use App\Entity\MenuLocation;
use App\Entity\MenuRoutes;
use App\Entity\MenuTranslation;
use App\Entity\MenuType as EnumMenuType;
use App\Entity\MenuVisibility;
use App\Form\MenuType;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Repository\MenuRepository;
use App\Repository\MenuTranslationRepository;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
class MenuController extends AbstractController
{
    public function __construct(
        private readonly MenuRepository $repo,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly MenuTranslationRepository $menuTransRepo,
        private readonly TranslationService $translationService,
        private readonly EventRepository $eventRepo,
        private readonly CmsRepository $cmsRepo,
    ) {}

    public function menuList(Request $request, ?int $edit = null): Response
    {
        $menu = $edit !== null ? $this->repo->find($edit) : new Menu();
        if ($edit !== null && $menu === null) {
            throw $this->createNotFoundException('Menu not found');
        }
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $type = EnumMenuType::from($form->get('type')->getData());
            $menu->setType($type);
            $menu->setLocation(MenuLocation::from($form->get('location')->getData()));
            $menu->setVisibility(MenuVisibility::from($form->get('visibility')->getData()));
            $menu->setPriority($this->getMenuPriorityForLocation($menu->getLocation()) + 1);
            switch ($type) {
                case EnumMenuType::Url:
                    $menu->setSlug($form->get('slug')->getData());
                    break;
                case EnumMenuType::Event:
                    $menu->setEvent($this->eventRepo->findOneBy(['id' => $form->get('event')->getData()]));
                    break;
                case EnumMenuType::Route:
                    $menu->setRoute(MenuRoutes::from($form->get('route')->getData()));
                    break;
                case EnumMenuType::Cms:
                    $menu->setCms($this->cmsRepo->findOneBy(['id' => $form->get('cms')->getData()]));
                    break;
            }

            foreach ($this->translationService->getLanguageCodes() as $languageCode) {
                $translation = $this->getTranslation($languageCode, $menu->getId());
                $translation->setMenu($menu);
                $translation->setLanguage($languageCode);
                $translation->setName($form->get("name-$languageCode")->getData());
                $this->entityManager->persist($translation);
            }

            $this->entityManager->persist($menu);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_admin_menu', ['edit' => $menu->getId()]);
        }

        return $this->render('admin_modules/cms/menu_index.html.twig', [
            'active' => 'menu',
            'form' => $form,
            'edit' => $edit,
            'menuTypeList' => EnumMenuType::getTranslatedList($this->translator),
            'menuTypeActive' => $menu?->getType()?->value ?? 0,
            'items' => $this->repo->getAllSlugified($this->getUser()),
            'menu_locations' => MenuLocation::getTranslatedList($this->translator),
        ]);
    }

    public function menuUp(int $id): Response
    {
        $this->adjustPriority($id, -1.5);

        return $this->redirectToRoute('app_admin_menu');
    }

    public function menuDown(int $id): Response
    {
        $this->adjustPriority($id, 1.5);

        return $this->redirectToRoute('app_admin_menu');
    }

    private function getTranslation(mixed $languageCode, ?int $getId): MenuTranslation
    {
        $translation = $this->menuTransRepo->findOneBy(['language' => $languageCode, 'menu' => $getId]);
        if ($translation !== null) {
            return $translation;
        }

        return new MenuTranslation();
    }

    public function menuDelete(int $id): Response
    {
        $menu = $this->repo->findOneBy(['id' => $id]);
        if ($menu !== null) {
            $translations = $this->menuTransRepo->findBy(['menu' => $menu->getId()]);
            foreach ($translations as $translation) {
                $this->entityManager->remove($translation);
            }
            $this->entityManager->remove($menu);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_menu');
    }

    private function adjustPriority(int $menuId, float $value): void
    {
        // update half up or down
        $subject = $this->repo->findOneBy(['id' => $menuId]);
        $subject->setPriority($subject->getPriority() + $value);
        $this->entityManager->persist($subject);
        $this->entityManager->flush();

        // loop through list and recount priority
        $priority = 1;
        $list = $this->repo->findBy(['location' => $subject->getLocation()], ['priority' => 'ASC']);
        foreach ($list as $menu) {
            $menu->setPriority($priority);
            ++$priority;
            $this->entityManager->persist($menu);
        }
        $this->entityManager->flush();
    }

    private function getMenuPriorityForLocation(?MenuLocation $getLocation): float
    {
        $menu = $this->repo->findOneBy(['location' => $getLocation], ['priority' => 'DESC']);

        return $menu?->getPriority() ?? 0;
    }
}
