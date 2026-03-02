<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Service\ImportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/import')]
class ImportController extends AbstractAdminController
{
    public function __construct(
        private readonly ImportService $importService,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    #[Route('', name: 'app_admin_system_import', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $file = $request->files->get('import_file');

            if ($file === null) {
                return $this->render('admin/system/import/index.html.twig', [
                    'active' => 'system',
                    'activeSection' => 'import',
                    'error' => 'No file uploaded.',
                ]);
            }

            if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
                return $this->render('admin/system/import/index.html.twig', [
                    'active' => 'system',
                    'activeSection' => 'import',
                    'error' => 'Only ZIP files are accepted.',
                ]);
            }

            try {
                $summary = $this->importService->import($file->getRealPath());

                return $this->render('admin/system/import/index.html.twig', [
                    'active' => 'system',
                    'activeSection' => 'import',
                    'summary' => $summary,
                ]);
            } catch (\RuntimeException $e) {
                return $this->render('admin/system/import/index.html.twig', [
                    'active' => 'system',
                    'activeSection' => 'import',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->render('admin/system/import/index.html.twig', [
            'active' => 'system',
            'activeSection' => 'import',
        ]);
    }
}
