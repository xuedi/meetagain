<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\ExtendedFilesystem;
use App\Portability\ArchiveReader;
use App\Portability\Importer;
use App\Portability\ImportSummary;
use App\Portability\KindLabels;
use App\Portability\Outcome;
use App\Repository\EventRepository;
use App\Service\Admin\CommandService;
use App\Service\Config\PluginService;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/import')]
final class ImportController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    private const string PENDING_ARCHIVE = 'admin_system_import_pending';

    public function __construct(
        TranslatorInterface $translator,
        private readonly Importer $importer,
        private readonly ArchiveReader $archiveReader,
        private readonly EventRepository $eventRepository,
        private readonly PluginService $pluginService,
        private readonly KindLabels $kindLabels,
        private readonly CommandService $commandService,
        private readonly ExtendedFilesystem $fs,
        #[Autowire('%kernel.project_dir%/var/import')]
        private readonly string $pendingDir,
    ) {
        parent::__construct($translator, 'import');
    }

    #[Route('', name: 'app_admin_system_import', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->renderPage();
        }

        $file = $request->files->get('import_file');
        if (!$file instanceof UploadedFile) {
            return $this->renderPage(error: $this->translator->trans('admin_system_import.error_no_file'));
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
            return $this->renderPage(error: $this->translator->trans('admin_system_import.error_not_zip'));
        }

        $this->discardPendingArchive($request->getSession());
        $pending = $file->move($this->pendingDir, bin2hex(random_bytes(16)) . '.zip')->getPathname();

        try {
            $archive = $this->archiveReader->describe($pending);
        } catch (RuntimeException $e) {
            $this->fs->deleteFile($pending);

            return $this->renderPage(error: $e->getMessage());
        }

        $request->getSession()->set(self::PENDING_ARCHIVE, $pending);

        return $this->renderPage(archive: $archive);
    }

    #[Route('/run', name: 'app_admin_system_import_run', methods: ['POST'])]
    public function run(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_system_import_run', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $pending = $request->getSession()->get(self::PENDING_ARCHIVE);
        if (!is_string($pending) || !$this->fs->fileExists($pending)) {
            return $this->renderPage(error: $this->translator->trans('admin_system_import.error_expired'));
        }

        try {
            $summary = $this->importer->import($pending, applySite: $request->request->getBoolean('site_settings'));
        } catch (RuntimeException $e) {
            return $this->renderPage(error: $e->getMessage());
        } finally {
            $this->discardPendingArchive($request->getSession());
        }

        if ($summary->siteApplied) {
            $this->commandService->rebuildTheme();
        }

        return $this->renderPage(summary: $summary);
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

        return $this->render('admin/system/import/index.html.twig', [
            'active' => 'system',
            'error' => $error,
            'archive' => $archive,
            'summary' => $summary,
            'outcomes' => Outcome::cases(),
            'labels' => $this->kindLabels->labelsFor($kinds),
            'pluginNames' => $pluginNames,
            'siteSettingsDefault' => $this->eventRepository->count([]) === 0,
            'adminTop' => $this->buildAdminTop($archive, $summary),
            'adminTabs' => $this->getTabs(),
        ]);
    }

    /**
     * @param array{missing_plugins: list<string>, ...}|null $archive
     */
    private function buildAdminTop(?array $archive, ?ImportSummary $summary): AdminTop
    {
        if ($summary instanceof ImportSummary) {
            $lost = array_sum($summary->getLosses());
            $tag = $lost > 0
                ? $this->tag('is-warning', $this->translator->trans('admin_system_import.tag_losses', ['%count%' => $lost]))
                : $this->tag('is-success', $this->translator->trans('admin_system_import.tag_nothing_lost'));

            return new AdminTop(info: [new AdminTopInfoText($this->translator->trans('admin_system_import.success_title')), $tag]);
        }

        if ($archive === null) {
            return new AdminTop(info: [new AdminTopInfoText($this->translator->trans('admin_system_import.help'))]);
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

    private function tag(string $variant, string $text): AdminTopInfoHtml
    {
        return new AdminTopInfoHtml(sprintf('<span class="tag %s is-medium">%s</span>', $variant, htmlspecialchars($text)));
    }

    private function discardPendingArchive(SessionInterface $session): void
    {
        $pending = $session->get(self::PENDING_ARCHIVE);
        if (is_string($pending) && $this->fs->fileExists($pending)) {
            $this->fs->deleteFile($pending);
        }

        $session->remove(self::PENDING_ARCHIVE);
    }
}
