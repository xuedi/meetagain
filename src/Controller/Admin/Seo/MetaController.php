<?php declare(strict_types=1);

namespace App\Controller\Admin\Seo;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Form\SeoSettingsType;
use App\Service\Config\ConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/seo')]
final class MetaController extends AbstractSeoController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly ConfigService $configService,
    ) {
        parent::__construct($translator, 'meta');
    }

    #[Route('/meta', name: 'app_admin_seo_meta', methods: ['GET', 'POST'])]
    public function meta(Request $request): Response
    {
        $form = $this->createForm(SeoSettingsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->configService->saveSeoForm($form->getData());
            $this->addFlash('success', $this->translator->trans('admin_seo_meta.flash_saved'));

            return $this->redirectToRoute('app_admin_seo_meta');
        }

        $adminTop = new AdminTop(info: [new AdminTopInfoText($this->translator->trans('admin_seo_meta.intro'))]);

        return $this->render('admin/seo/meta/index.html.twig', [
            'active' => 'seo',
            'form' => $form,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }
}
