<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\Host;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Filter\Admin\Host\AdminHostListFilterService;
use App\Form\HostType;
use App\Repository\EventRepository;
use App\Repository\HostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ORGANIZER'), Route('/admin/hosts')]
final class HostController extends AbstractController implements AdminNavigationInterface
{
    public function __construct(
        private readonly HostRepository $repo,
        private readonly EventRepository $eventRepo,
        private readonly EntityActionDispatcher $entityActionDispatcher,
        private readonly AdminHostListFilterService $hostFilterService,
        private readonly TranslatorInterface $translator,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_content',
            links: [
                new AdminLink(
                    label: 'admin_shell.menu_host',
                    route: 'app_admin_host',
                    active: 'host',
                    role: 'ROLE_ORGANIZER',
                ),
            ],
            sectionPriority: 50,
        );
    }

    #[Route('', name: 'app_admin_host')]
    public function list(): Response
    {
        $filterResult = $this->hostFilterService->getHostIdFilter();
        $hosts = $this->repo->findAllForAdmin($filterResult->getHostIds());

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%d</strong>&nbsp;%s',
                    count($hosts),
                    $this->translator->trans('admin_host.summary_total'),
                )),
            ],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_host.page_title_create'),
                    target: $this->generateUrl('app_admin_host_add'),
                    icon: 'plus',
                ),
            ],
        );

        return $this->render('admin/host/list.html.twig', [
            'active' => 'host',
            'hosts' => $hosts,
            'adminTop' => $adminTop,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_host_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Host $host, EntityManagerInterface $entityManager): Response
    {
        if (!$this->hostFilterService->isHostAccessible($host->getId())) {
            throw $this->createNotFoundException('Host not found in current context.');
        }

        $form = $this->createForm(HostType::class, $host);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', $this->translator->trans('admin_host.flash_updated'));

            return $this->redirectToRoute('app_admin_host_edit', ['id' => $host->getId()]);
        }

        $eventsUsingHost = $this->eventRepo->findByHost($host);

        return $this->render('admin/host/edit.html.twig', [
            'active' => 'host',
            'host' => $host,
            'form' => $form,
            'adminTop' => $this->buildEditTop($host, count($eventsUsingHost)),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_host_delete', methods: ['GET'])]
    public function delete(Host $host, EntityManagerInterface $entityManager): Response
    {
        if (!$this->hostFilterService->isHostAccessible($host->getId())) {
            throw $this->createNotFoundException('Host not found in current context.');
        }

        // Safe to delete regardless of events: the event_host join table has ON DELETE CASCADE,
        // and Event has no direct FK to Host, so events stay intact and just lose this host
        // from their host collection.
        $hostId = $host->getId();
        $entityManager->remove($host);
        $entityManager->flush();

        $this->entityActionDispatcher->dispatch(EntityAction::DeleteHost, $hostId);

        $this->addFlash('success', $this->translator->trans('admin_host.flash_deleted'));

        return $this->redirectToRoute('app_admin_host');
    }

    #[Route('/add', name: 'app_admin_host_add', methods: ['GET', 'POST'])]
    public function add(Request $request, EntityManagerInterface $entityManager): Response
    {
        $host = new Host();
        $form = $this->createForm(HostType::class, $host);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($host);
            $entityManager->flush();

            $this->entityActionDispatcher->dispatch(EntityAction::CreateHost, $host->getId());

            $this->addFlash('success', $this->translator->trans('admin_host.flash_created'));

            return $this->redirectToRoute('app_admin_host_edit', ['id' => $host->getId()]);
        }

        return $this->render('admin/host/edit.html.twig', [
            'active' => 'host',
            'host' => $host,
            'form' => $form,
            'adminTop' => $this->buildEditTop($host, 0),
        ]);
    }

    private function buildEditTop(Host $host, int $eventsUsingCount): AdminTop
    {
        $isNew = $host->getId() === null;

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%s</strong>',
                htmlspecialchars(
                    $isNew
                        ? $this->translator->trans('admin_host.page_title_create')
                        : ($host->getName() ?? ''),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8',
                ),
            )),
        ];

        if (!$isNew && $eventsUsingCount > 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $eventsUsingCount,
                $this->translator->trans('admin_host.summary_events_using'),
            ));
        }

        $actions = [];
        if (!$isNew) {
            $actions[] = new AdminTopActionButton(
                label: $this->translator->trans('global.button_delete'),
                target: $this->generateUrl('app_admin_host_delete', ['id' => $host->getId()]),
                icon: 'trash',
                variant: 'is-danger',
                confirm: $this->translator->trans('admin_host.warning_delete_confirm'),
            );
        }
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('global.button_back'),
            target: $this->generateUrl('app_admin_host'),
            icon: 'arrow-left',
        );

        return new AdminTop(info: $info, actions: $actions);
    }
}
