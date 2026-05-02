<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Form\SeoSettingsType;
use App\Service\Config\ConfigService;
use App\Service\Seo\IndexNowService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system')]
final class SeoController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly ConfigService $configService,
        private readonly IndexNowService $indexNowService,
    ) {
        parent::__construct($translator, 'seo');
    }

    #[Route('/seo', name: 'app_admin_system_seo', methods: ['GET', 'POST'])]
    public function seo(Request $request): Response
    {
        $form = $this->createForm(SeoSettingsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->configService->saveSeoForm($form->getData());
            $this->addFlash('success', $this->translator->trans('admin_system_seo.flash_saved'));

            return $this->redirectToRoute('app_admin_system_seo');
        }

        $adminTop = new AdminTop(
            info: [new AdminTopInfoText($this->translator->trans('admin_system_seo.intro'))],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_system_seo.button_submit_indexnow'),
                    target: $this->generateUrl('app_admin_system_seo_indexnow_submit'),
                    icon: 'paper-plane',
                    variant: 'is-warning',
                    confirm: $this->translator->trans('admin_system_seo.confirm_submit_indexnow'),
                ),
            ],
        );

        return $this->render('admin/system/seo/index.html.twig', [
            'active' => 'system',
            'form' => $form,
            'indexNowKey' => $this->indexNowService->getOrCreateKey(),
            'lastSubmittedAt' => $this->indexNowService->getLastSubmittedAt(),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/seo/indexnow-submit', name: 'app_admin_system_seo_indexnow_submit', methods: ['GET'])]
    public function indexNowSubmit(): Response
    {
        $result = $this->indexNowService->submit();
        $status = $result['status'];

        if ($status === 200 || $status === 202) {
            $this->indexNowService->recordSubmission();
            $this->addFlash('success', $this->translator->trans('admin_system_seo.flash_indexnow_submitted', ['%status%' => $status]));
        } else {
            $this->addFlash('error', $this->translator->trans('admin_system_seo.flash_indexnow_failed', ['%status%' => $status]));
        }

        return $this->redirectToRoute('app_admin_system_seo');
    }
}
