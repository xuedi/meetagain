<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\DataHotfix\DeferredException;
use App\DataHotfix\Runner;
use App\Security\Permission\Attribute\PermissionAttribute;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[Route('/admin/system/hotfixes')]
#[IsGranted('ROLE_ADMIN')]
final class HotfixController extends AbstractSettingsController
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly Runner $runner,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($translator, 'hotfixes');
    }

    #[Route('', name: 'app_admin_system_hotfixes', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SETTINGS_READ);

        $hotfixes = [];
        foreach ($this->runner->all() as $hotfix) {
            $ranAt = $this->runner->ranAt($hotfix);
            $hotfixes[] = [
                'identifier' => $hotfix->getIdentifier(),
                'ranAt' => $ranAt !== null ? new DateTimeImmutable($ranAt) : null,
            ];
        }
        $pending = count(array_filter($hotfixes, static fn(array $row): bool => $row['ranAt'] === null));

        $adminTop = new AdminTop(info: [
            new AdminTopInfoText($this->translator->trans('admin_system_hotfix.intro')),
            new AdminTopInfoText($this->translator->trans('admin_system_hotfix.info_pending', ['%pending%' => $pending, '%total%' => count($hotfixes)])),
        ]);

        return $this->render('admin/system/hotfix/index.html.twig', [
            'active' => 'system',
            'hotfixes' => $hotfixes,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{identifier}/run', name: 'app_admin_system_hotfix_run', requirements: ['identifier' => '[a-z0-9_]+'], methods: ['POST'])]
    public function run(string $identifier, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SETTINGS_UPDATE);
        if (!$this->isCsrfTokenValid('app_admin_system_hotfix_run' . $identifier, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        $hotfix = $this->runner->find($identifier);
        if ($hotfix === null) {
            throw $this->createNotFoundException();
        }

        if ($this->runner->ranAt($hotfix) === null) {
            try {
                $this->runner->run($hotfix);
            } catch (DeferredException $e) {
                $this->addFlash('warning', $this->translator->trans('admin_system_hotfix.flash_deferred', [
                    '%hotfix%' => $identifier,
                    '%reason%' => $e->getMessage(),
                ]));
            } catch (Throwable $e) {
                $this->logger->error('Data hotfix failed', ['identifier' => $identifier, 'exception' => $e]);
                $this->addFlash('error', $this->translator->trans('admin_system_hotfix.flash_failed', [
                    '%hotfix%' => $identifier,
                    '%reason%' => $e->getMessage(),
                ]));
            }
        }

        return $this->redirectToRoute('app_admin_system_hotfixes');
    }
}
