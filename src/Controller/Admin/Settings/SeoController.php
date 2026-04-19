<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Form\SeoSettingsType;
use App\Service\Config\ConfigService;
use App\Service\Seo\IndexNowService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system')]
final class SeoController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly ConfigService $configService,
        private readonly IndexNowService $indexNowService,
    ) {}

    #[Route('/seo', name: 'app_admin_system_seo', methods: ['GET', 'POST'])]
    public function seo(Request $request): Response
    {
        $form = $this->createForm(SeoSettingsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->configService->saveSeoForm($form->getData());
            $this->addFlash('success', 'SEO settings saved');
        }

        return $this->render('admin/system/seo/index.html.twig', [
            'active' => 'system',
            'form' => $form,
            'indexNowKey' => $this->indexNowService->getOrCreateKey(),
            'lastSubmittedAt' => $this->indexNowService->getLastSubmittedAt(),
        ]);
    }

    #[Route('/seo/indexnow-submit', name: 'app_admin_system_seo_indexnow_submit', methods: ['POST'])]
    public function indexNowSubmit(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('indexnow_submit', $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token');

            return $this->redirectToRoute('app_admin_system_seo');
        }

        $result = $this->indexNowService->submit();
        $status = $result['status'];

        if ($status === 200 || $status === 202) {
            $this->indexNowService->recordSubmission();
            $this->addFlash('success', sprintf('Submitted to IndexNow (HTTP %d)', $status));
        }
        if ($status !== 200 && $status !== 202) {
            $this->addFlash('danger', sprintf('IndexNow submission failed (HTTP %d)', $status));
        }

        return $this->redirectToRoute('app_admin_system_seo');
    }
}
