<?php declare(strict_types=1);

namespace App\Controller\Admin\Seo;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionForm;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Service\Seo\IndexNowService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/seo')]
final class IndexNowController extends AbstractSeoController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly IndexNowService $indexNowService,
    ) {
        parent::__construct($translator, 'indexnow');
    }

    #[Route('/indexnow', name: 'app_admin_seo_indexnow', methods: ['GET'])]
    public function indexNow(): Response
    {
        $adminTop = new AdminTop(info: [new AdminTopInfoText($this->translator->trans('admin_seo_indexnow.intro'))], actions: [
            new AdminTopActionForm(
                label: $this->translator->trans('admin_seo_indexnow.button_submit'),
                target: $this->generateUrl('app_admin_seo_indexnow_submit'),
                csrfTokenId: 'admin_seo_indexnow_submit',
                icon: 'paper-plane',
                variant: 'is-warning',
                confirm: $this->translator->trans('admin_seo_indexnow.confirm_submit'),
            ),
        ]);

        return $this->render('admin/seo/indexnow/index.html.twig', [
            'active' => 'seo',
            'indexNowKey' => $this->indexNowService->getOrCreateKey(),
            'lastSubmittedAt' => $this->indexNowService->getLastSubmittedAt(),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/indexnow/submit', name: 'app_admin_seo_indexnow_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_seo_indexnow_submit', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $result = $this->indexNowService->submit();
        $status = $result['status'];

        if ($status === 200 || $status === 202) {
            $this->indexNowService->recordSubmission();
            $this->addFlash('success', $this->translator->trans('admin_seo_indexnow.flash_submitted', [
                '%status%' => $status,
            ]));
        } else {
            $this->addFlash('error', $this->translator->trans('admin_seo_indexnow.flash_failed', [
                '%status%' => $status,
            ]));
        }

        return $this->redirectToRoute('app_admin_seo_indexnow');
    }
}
