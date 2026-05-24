<?php declare(strict_types=1);

namespace Plugin\Ranking\Controller\Admin;

use App\Entity\User;
use Plugin\Ranking\Service\Import\RankCsvImporter;
use Plugin\Ranking\Service\RankingConfigService;
use Plugin\Ranking\ValueObject\RankImportReport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/plugin/ranking/import')]
final class RankImportController extends AbstractController
{
    public function __construct(
        private readonly RankingConfigService $configService,
        private readonly RankCsvImporter $importer,
    ) {}

    #[Route('', name: 'app_plugin_ranking_admin_import', methods: ['GET', 'POST'])]
    public function upload(Request $request): Response
    {
        $report = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ranking_csv_import', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'global.csrf_invalid');

                return $this->redirectToRoute('app_plugin_ranking_admin_import');
            }

            $config = $this->configService->getOrCreateForCurrentGroup();
            $file = $request->files->get('csv');
            if (!$file instanceof UploadedFile) {
                $this->addFlash('warning', 'ranking_admin_import.flash_completed');

                return $this->redirectToRoute('app_plugin_ranking_admin_import');
            }

            $actor = $this->getUser();
            \assert($actor instanceof User);

            $report = $this->importer->import($file->getPathname(), $config, $actor);
            $this->addFlash('success', 'ranking_admin_import.flash_completed');
        }

        return $this->render('@Ranking/admin/import/upload.html.twig', [
            'report' => $report,
            'active' => 'plugin',
        ]);
    }
}
