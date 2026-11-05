<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Portability\ArchiveReader;
use App\Portability\ExportAction;
use App\Portability\Importer;
use App\Portability\ImportSummary;
use App\Portability\KindLabels;
use App\Portability\Outcome;
use App\Service\Config\PluginService;
use App\Service\Event\EventService;
use App\Service\System\CleanupService;
use League\Flysystem\FilesystemOperator;
use Module\Task\Contract\ActionQueueInterface;
use Module\Task\Contract\RequestedAction;
use Module\Task\Contract\Status;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/system/import')]
#[IsGranted('ROLE_ADMIN')]
final class ImportController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    private const string PENDING_ARCHIVE = 'admin_system_import_pending';
    private const string EXPORT_TOKEN = 'admin_system_import_export';
    private const string DELETE_EXPORT_TOKEN = 'admin_system_import_export_delete';

    public function __construct(
        TranslatorInterface $translator,
        private readonly Importer $importer,
        private readonly ArchiveReader $archiveReader,
        private readonly EventService $eventService,
        private readonly PluginService $pluginService,
        private readonly KindLabels $kindLabels,
        private readonly FilesystemOperator $privateStorage,
        private readonly ExportAction $exportAction,
        private readonly ActionQueueInterface $actionQueue,
    ) {
        parent::__construct($translator, 'import');
    }

    #[Route('', name: 'app_admin_system_import', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->renderPage();
        }

        if (!$this->isCsrfTokenValid('admin_system_import_upload', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $file = $request->files->get('import_file');
        if (!$file instanceof UploadedFile) {
            return $this->renderPage(error: $this->translator->trans('admin_system_import.error_no_file'));
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
            return $this->renderPage(error: $this->translator->trans('admin_system_import.error_not_zip'));
        }

        $this->discardPendingArchive($request->getSession());

        try {
            $archive = $this->archiveReader->describe($file->getPathname());
        } catch (RuntimeException $e) {
            return $this->renderPage(error: $e->getMessage());
        }

        $pendingId = bin2hex(random_bytes(16));
        $stream = fopen($file->getPathname(), 'rb');
        if ($stream === false) {
            return $this->renderPage(error: $this->translator->trans('admin_system_import.error_no_file'));
        }
        try {
            $this->privateStorage->writeStream((string) $this->pendingKey($pendingId), $stream);
        } finally {
            fclose($stream);
        }

        $request->getSession()->set(self::PENDING_ARCHIVE, $pendingId);

        return $this->renderPage(archive: $archive);
    }

    #[Route('/run', name: 'app_admin_system_import_run', methods: ['POST'])]
    public function run(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_system_import_run', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $pending = $this->pendingKey($request->getSession()->get(self::PENDING_ARCHIVE));
        if ($pending === null || !$this->privateStorage->fileExists($pending)) {
            return $this->renderPage(error: $this->translator->trans('admin_system_import.error_expired'));
        }

        $copy = (string) tempnam(sys_get_temp_dir(), 'meetagain-import-');
        try {
            file_put_contents($copy, $this->privateStorage->readStream($pending));
            $summary = $this->importer->import($copy, applySite: $request->request->getBoolean('site_settings'));
        } catch (RuntimeException $e) {
            return $this->renderPage(error: $e->getMessage());
        } finally {
            unlink($copy);
            $this->discardPendingArchive($request->getSession());
        }

        return $this->renderPage(summary: $summary);
    }

    #[Route('/export', name: 'app_admin_system_import_export', methods: ['POST'])]
    public function export(Request $request): Response
    {
        if (!$this->isCsrfTokenValid(self::EXPORT_TOKEN, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $latest = $this->actionQueue->latest(ExportAction::IDENTIFIER);
        if ($latest === null || $latest->isFinished()) {
            $this->actionQueue->queue(ExportAction::IDENTIFIER, [], $this->getUser()?->getUserIdentifier());
        }

        return $this->redirectToRoute('app_admin_system_import');
    }

    #[Route('/export/delete', name: 'app_admin_system_import_export_delete', methods: ['POST'])]
    public function deleteExport(Request $request): Response
    {
        if (!$this->isCsrfTokenValid(self::DELETE_EXPORT_TOKEN, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->privateStorage->delete(ExportAction::ARCHIVE);

        return $this->redirectToRoute('app_admin_system_import');
    }

    #[Route('/export/download', name: 'app_admin_system_import_export_download', methods: ['GET'])]
    public function download(): Response
    {
        $archive = $this->exportAction->archive();
        if ($archive === null) {
            throw $this->createNotFoundException();
        }

        $stream = $this->privateStorage->readStream(ExportAction::ARCHIVE);
        $response = new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        });
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Length', (string) $archive['size']);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, sprintf(
            'meetagain-export-%s.zip',
            $archive['writtenAt']->format('Y-m-d'),
        )));

        return $response;
    }

    /**
     * @param array{name: string, exported_at: string, description: string, plugins: list<string>, missing_plugins: list<string>, counts: array<string, int>, ...}|null $archive
     */
    private function renderPage(?string $error = null, ?array $archive = null, ?ImportSummary $summary = null): Response
    {
        $kinds = array_keys($archive['counts'] ?? $summary->counts ?? []);
        $pluginNames = [];
        foreach ($archive['plugins'] ?? [] as $pluginKey) {
            $pluginNames[$pluginKey] = $this->pluginService->getName($pluginKey);
        }

        $latestExport = $this->actionQueue->latest(ExportAction::IDENTIFIER);
        $exportArchive = $this->exportAction->archive();

        return $this->render('admin/system/import/index.html.twig', [
            'active' => 'system',
            'error' => $error,
            'archive' => $archive,
            'summary' => $summary,
            'outcomes' => Outcome::cases(),
            'labels' => $this->kindLabels->labelsFor($kinds),
            'pluginNames' => $pluginNames,
            'siteSettingsDefault' => !$this->eventService->hasEvents(),
            'exportArchive' => $exportArchive,
            'exportInProgress' => $latestExport !== null && !$latestExport->isFinished(),
            'exportFailure' => $latestExport?->isFinished() && $latestExport->status !== Status::ok ? $latestExport->message : null,
            'exportMaxHours' => CleanupService::EXPORT_MAX_HOURS,
            'adminTop' => $this->buildAdminTop($archive, $summary, $latestExport),
            'adminTabs' => $this->getTabs(),
        ]);
    }

    /**
     * @param array{missing_plugins: list<string>, ...}|null $archive
     */
    private function buildAdminTop(?array $archive, ?ImportSummary $summary, ?RequestedAction $latestExport): AdminTop
    {
        if ($summary instanceof ImportSummary) {
            $lost = array_sum($summary->getLosses());
            $tag = $lost > 0
                ? $this->tag('is-warning', $this->translator->trans('admin_system_import.tag_losses', ['%count%' => $lost]))
                : $this->tag('is-success', $this->translator->trans('admin_system_import.tag_nothing_lost'));

            return $this->withExportTag([new AdminTopInfoText($this->translator->trans('admin_system_import.success_title')), $tag], $latestExport);
        }

        if ($archive === null) {
            return $this->withExportTag([new AdminTopInfoText($this->translator->trans('admin_system_import.help'))], $latestExport);
        }

        $info = [new AdminTopInfoText($this->translator->trans('admin_system_import.help_preview'))];
        $missing = count($archive['missing_plugins']);
        if ($missing > 0) {
            $info[] = new AdminTopInfoText($this->translator->trans('admin_system_import.help_missing_plugins'));
            $info[] = $this->tag('is-warning', $this->translator->trans('admin_system_import.tag_missing_plugins', ['%count%' => $missing]));
        }

        return new AdminTop(info: $info, actions: [
            new AdminTopActionButton(
                label: $this->translator->trans('global.button_back'),
                target: $this->generateUrl('app_admin_system_import'),
                icon: 'arrow-left',
            ),
        ]);
    }

    /**
     * @param list<AdminTopInfoText|AdminTopInfoHtml> $info
     */
    private function withExportTag(array $info, ?RequestedAction $latest): AdminTop
    {
        $exportTag = $this->exportTag($latest);
        if ($exportTag !== null) {
            $info[] = $exportTag;
        }

        return new AdminTop(info: $info);
    }

    private function exportTag(?RequestedAction $latest): ?AdminTopInfoHtml
    {
        return match (true) {
            $latest === null => null,
            $latest->isRunning() => $this->tag('is-warning', $this->translator->trans('admin_system_import.tag_export_running')),
            !$latest->isFinished() => $this->tag('is-warning', $this->translator->trans('admin_system_import.tag_export_queued')),
            $latest->status !== Status::ok => $this->tag('is-danger', $this->translator->trans('admin_system_import.tag_export_failed')),
            default => null,
        };
    }

    private function tag(string $variant, string $text): AdminTopInfoHtml
    {
        return new AdminTopInfoHtml(sprintf('<span class="tag %s is-medium">%s</span>', $variant, htmlspecialchars($text)));
    }

    private function discardPendingArchive(SessionInterface $session): void
    {
        $pending = $this->pendingKey($session->get(self::PENDING_ARCHIVE));
        if ($pending !== null) {
            $this->privateStorage->delete($pending);
        }

        $session->remove(self::PENDING_ARCHIVE);
    }

    private function pendingKey(mixed $pendingId): ?string
    {
        return is_string($pendingId) && preg_match('/^[a-f0-9]{32}$/', $pendingId) === 1 ? Importer::PENDING_DIRECTORY . '/' . $pendingId . '.zip' : null;
    }
}
