<?php

declare(strict_types=1);

namespace App\Controller\Admin\Support;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\SupportRequest;
use App\Enum\SupportRequestStatus;
use App\Repository\SupportRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/support')]
final class RequestsController extends AbstractSupportController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly SupportRequestRepository $supportRequestRepo,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($translator, 'requests');
    }

    #[Route('', name: 'app_admin_support_list')]
    public function list(): Response
    {
        $requests = $this->supportRequestRepo
            ->createQueryBuilder('sr')
            ->orderBy('sr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $newCount = 0;
        foreach ($requests as $request) {
            if (!$request->isNew()) {
                continue;
            }

            $newCount++;
        }
        $totalCount = count($requests);

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $totalCount,
                $this->translator->trans('admin_support.summary_total_requests'),
            )),
        ];
        $info[] = $newCount > 0
            ? new AdminTopInfoHtml(sprintf(
                '<span class="tag is-warning is-medium">%d&nbsp;%s</span>',
                $newCount,
                $this->translator->trans('admin_support.summary_new_requests'),
            ))
            : new AdminTopInfoHtml(sprintf(
                '<span class="tag is-success is-medium">%s</span>',
                $this->translator->trans('admin_support.summary_all_read'),
            ));

        $adminTop = new AdminTop(info: $info);

        return $this->render('admin/support/list.html.twig', [
            'active' => 'support',
            'requests' => $requests,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_support_request_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $request = $this->supportRequestRepo->find($id);
        if (!$request instanceof SupportRequest) {
            throw $this->createNotFoundException();
        }

        $statusVariant = $request->isNew() ? 'is-warning' : 'is-light';
        $statusKey = $request->isNew() ? 'admin_support.status_new' : 'admin_support.status_read';

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%s</strong>', $request->getCreatedAt()->format('Y-m-d H:i:s'))),
            new AdminTopInfoHtml(sprintf('<span class="tag is-light is-medium">%s</span>', htmlspecialchars(
                $request->getContactType()->label(),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            ))),
            new AdminTopInfoHtml(sprintf(
                '<span class="tag %s is-medium">%s</span>',
                $statusVariant,
                htmlspecialchars($this->translator->trans($statusKey), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            )),
        ];

        $actions = [];
        if ($request->isNew()) {
            $actions[] = new AdminTopActionButton(
                label: $this->translator->trans('admin_support.button_mark_read'),
                target: $this->generateUrl('app_admin_support_mark_read', ['id' => $request->getId()]),
                icon: 'check',
                variant: 'is-warning',
            );
        }
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('admin_support.button_back'),
            target: $this->generateUrl('app_admin_support_list'),
            icon: 'arrow-left',
        );

        $adminTop = new AdminTop(info: $info, actions: $actions);

        return $this->render('admin/support/request_show.html.twig', [
            'active' => 'support',
            'request' => $request,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/mark-read/{id}', name: 'app_admin_support_mark_read', requirements: ['id' => '\d+'])]
    public function markRead(int $id): Response
    {
        $request = $this->supportRequestRepo->find($id);
        if ($request instanceof SupportRequest) {
            $request->setStatus(SupportRequestStatus::Read);
            $this->em->persist($request);
            $this->em->flush();
        }

        return $this->redirectToRoute('app_admin_support_list');
    }
}
