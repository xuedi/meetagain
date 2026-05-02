<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Service\System\ImportService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/import')]
final class ImportController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly ImportService $importService,
    ) {
        parent::__construct($translator, 'import');
    }

    #[Route('', name: 'app_admin_system_import', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $error = null;
        $summary = null;

        if ($request->isMethod('POST')) {
            $file = $request->files->get('import_file');

            if ($file === null) {
                $error = $this->translator->trans('admin_system_import.error_no_file');
            } elseif (strtolower($file->getClientOriginalExtension()) !== 'zip') {
                $error = $this->translator->trans('admin_system_import.error_not_zip');
            } else {
                try {
                    $summary = $this->importService->import($file->getRealPath());
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $adminTop = new AdminTop(
            info: [new AdminTopInfoText($this->translator->trans('admin_system_import.help'))],
        );

        return $this->render('admin/system/import/index.html.twig', [
            'active' => 'system',
            'summary' => $summary,
            'error' => $error,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }
}
