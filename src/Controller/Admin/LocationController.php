<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\Location;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Filter\Admin\Location\AdminLocationListFilterService;
use App\Form\LocationType;
use App\Repository\EventRepository;
use App\Repository\LocationRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ORGANIZER'), Route('/admin/locations')]
final class LocationController extends AbstractController implements AdminNavigationInterface
{
    public function __construct(
        private readonly LocationRepository $repo,
        private readonly EventRepository $eventRepo,
        private readonly EntityActionDispatcher $entityActionDispatcher,
        private readonly AdminLocationListFilterService $locationFilterService,
        private readonly TranslatorInterface $translator,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_content',
            links: [
                new AdminLink(
                    label: 'admin_shell.menu_location',
                    route: 'app_admin_location',
                    active: 'location',
                    role: 'ROLE_ORGANIZER',
                ),
            ],
            sectionPriority: 50,
        );
    }

    #[Route('', name: 'app_admin_location')]
    public function list(): Response
    {
        $filterResult = $this->locationFilterService->getLocationIdFilter();
        $locations = $this->repo->findAllForAdmin($filterResult->getLocationIds());

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%d</strong>&nbsp;%s',
                    count($locations),
                    $this->translator->trans('admin_location.summary_total'),
                )),
            ],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_location.page_title_create'),
                    target: $this->generateUrl('app_admin_location_add'),
                    icon: 'plus',
                ),
            ],
        );

        return $this->render('admin/location/list.html.twig', [
            'active' => 'location',
            'locations' => $locations,
            'adminTop' => $adminTop,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_location_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Location $location, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::LOCATION_UPDATE, $location);

        if (!$this->locationFilterService->isLocationAccessible($location->getId())) {
            throw $this->createNotFoundException('Location not found in current context.');
        }

        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->entityActionDispatcher->dispatch(EntityAction::UpdateLocation, $location->getId());

            $this->addFlash('success', $this->translator->trans('admin_location.flash_updated'));

            return $this->redirectToRoute('app_admin_location_edit', ['id' => $location->getId()]);
        }

        $eventsUsingLocation = $this->eventRepo->findBy(['location' => $location]);

        return $this->render('admin/location/edit.html.twig', [
            'active' => 'location',
            'location' => $location,
            'form' => $form,
            'events_using_location' => $eventsUsingLocation,
            'adminTop' => $this->buildEditTop($location, count($eventsUsingLocation)),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_location_delete', methods: ['GET'])]
    public function delete(Location $location, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::LOCATION_DELETE, $location);

        if (!$this->locationFilterService->isLocationAccessible($location->getId())) {
            throw $this->createNotFoundException('Location not found in current context.');
        }

        $eventsUsingLocation = $this->eventRepo->findBy(['location' => $location]);
        if (count($eventsUsingLocation) > 0) {
            $this->addFlash(
                'error',
                $this->translator->trans('admin_location.flash_delete_blocked', ['%count%' => count($eventsUsingLocation)]),
            );

            return $this->redirectToRoute('app_admin_location_edit', ['id' => $location->getId()]);
        }

        $locationId = $location->getId();
        $entityManager->remove($location);
        $entityManager->flush();

        $this->entityActionDispatcher->dispatch(EntityAction::DeleteLocation, $locationId);

        $this->addFlash('success', $this->translator->trans('admin_location.flash_deleted'));

        return $this->redirectToRoute('app_admin_location');
    }

    #[Route('/add', name: 'app_admin_location_add', methods: ['GET', 'POST'])]
    public function add(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::LOCATION_CREATE);

        $location = new Location();
        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $location->setCreatedAt(new DateTimeImmutable());
            $location->setUser($this->getUser());

            $entityManager->persist($location);
            $entityManager->flush();

            $this->entityActionDispatcher->dispatch(EntityAction::CreateLocation, $location->getId());

            $this->addFlash('success', $this->translator->trans('admin_location.flash_created'));

            return $this->redirectToRoute('app_admin_location_edit', ['id' => $location->getId()]);
        }

        return $this->render('admin/location/edit.html.twig', [
            'active' => 'location',
            'location' => $location,
            'form' => $form,
            'events_using_location' => [],
            'adminTop' => $this->buildEditTop($location, 0),
        ]);
    }

    private function buildEditTop(Location $location, int $eventsUsingCount): AdminTop
    {
        $isNew = $location->getId() === null;

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%s</strong>',
                htmlspecialchars(
                    $isNew
                        ? $this->translator->trans('admin_location.page_title_create')
                        : ($location->getName() ?? ''),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8',
                ),
            )),
        ];

        if (!$isNew && $eventsUsingCount > 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $eventsUsingCount,
                $this->translator->trans('admin_location.summary_events_using'),
            ));
        }

        return new AdminTop(
            info: $info,
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('global.button_back'),
                    target: $this->generateUrl('app_admin_location'),
                    icon: 'arrow-left',
                ),
            ],
        );
    }
}
