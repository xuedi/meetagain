<?php declare(strict_types=1);

namespace Module\Trust\Internal\Controller;

use App\Admin\Navigation\AdminLink;
use App\Admin\Navigation\AdminNavigationConfig;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Controller\AbstractController;
use InvalidArgumentException;
use Module\Trust\Contract\TrustConfig;
use Module\Trust\Internal\ActionRegistry;
use Module\Trust\Internal\ConfigStore;
use Module\Trust\Internal\ContextRegistry;
use Module\Trust\Internal\RowBuilder;
use Module\Trust\Internal\ScoreProvider;
use Override;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/trust')]
final class AdminController extends AbstractController implements AdminNavigationInterface
{
    public function __construct(
        private readonly ContextRegistry $registry,
        private readonly ActionRegistry $actionRegistry,
        private readonly ConfigStore $configStore,
        private readonly ScoreProvider $scoreProvider,
        private readonly RowBuilder $rowBuilder,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return new AdminNavigationConfig(
            section: 'admin_shell.section_system',
            links: [new AdminLink(label: 'trust.admin_nav', route: 'app_admin_trust', active: 'trust')],
        );
    }

    #[Route('', name: 'app_admin_trust')]
    public function index(): Response
    {
        $contexts = [];
        foreach ($this->registry->describeAll() as $descriptor) {
            $contexts[] = [
                'descriptor' => $descriptor,
                'members' => count($this->scoreProvider->getMap($descriptor->context)),
                'configured' => $this->configStore->isConfigured($descriptor->context),
            ];
        }

        $adminTop = new AdminTop(info: [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($contexts),
                $this->translator->trans('trust.admin_contexts'),
            )),
        ]);

        return $this->render('@Trust/admin/index.html.twig', [
            'active' => 'trust',
            'adminTop' => $adminTop,
            'contexts' => $contexts,
        ]);
    }

    #[Route('/context', name: 'app_admin_trust_context', methods: ['GET'])]
    public function context(Request $request): Response
    {
        $context = $request->query->getString('context');
        $descriptor = $this->registry->describe($context);
        if ($descriptor === null) {
            throw $this->createNotFoundException();
        }

        $config = $this->configStore->get($context);
        $viewerId = $this->getAuthedUser()->getId() ?? 0;
        $rows = $this->rowBuilder->build($context, $viewerId, true);
        $highest = $rows === [] ? 0 : max(array_map(static fn(array $row): int => $row['score'] ?? 0, $rows));

        $adminTop = new AdminTop(
            info: [new AdminTopInfoHtml(sprintf('<strong>%s</strong>', htmlspecialchars($descriptor->label, ENT_QUOTES)))],
            actions: [new AdminTopActionButton(
                label: $this->translator->trans('global.button_back'),
                target: $this->generateUrl('app_admin_trust'),
                icon: 'arrow-left',
            )],
        );

        $actions = [];
        foreach ($this->actionRegistry->forContext($context) as $key => $actionDescriptor) {
            $actions[] = [
                'key' => $key,
                'label' => $actionDescriptor->label,
                'points' => $config->pointsFor($actionDescriptor),
                'cap' => $config->capFor($actionDescriptor),
                'default' => $actionDescriptor->defaultPoints,
            ];
        }

        return $this->render('@Trust/admin/context.html.twig', [
            'active' => 'trust',
            'adminTop' => $adminTop,
            'descriptor' => $descriptor,
            'config' => $config,
            'rows' => $rows,
            'actions' => $actions,
            'undeclared' => $this->scoreProvider->findUndeclaredActions($context),
            'minimumUnreachable' => $config->minimumToParticipate > $highest,
        ]);
    }

    #[Route('/context/save', name: 'app_admin_trust_context_save', methods: ['POST'])]
    public function save(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('trust_context_save', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $context = $request->request->getString('context');
        if (!$this->registry->exists($context)) {
            throw $this->createNotFoundException();
        }

        $descriptors = $this->actionRegistry->forContext($context);
        $points = [];
        $caps = [];
        foreach ($request->request->all('actions') as $submitted) {
            $key = is_array($submitted) ? (string) ($submitted['key'] ?? '') : '';
            if (!isset($descriptors[$key])) {
                continue;
            }
            $points[$key] = (int) ($submitted['points'] ?? 0);
            if (($submitted['cap'] ?? '') !== '') {
                $caps[$key] = (int) $submitted['cap'];
            }
        }

        try {
            $this->configStore->save($context, new TrustConfig(
                maxScore: $request->request->getInt('maxScore'),
                percentSlight: $request->request->getInt('percentSlight'),
                percentTrusted: $request->request->getInt('percentTrusted'),
                percentAbsolute: $request->request->getInt('percentAbsolute'),
                rootPointsPrimary: $request->request->getInt('rootPointsPrimary'),
                rootPointsSecondary: $request->request->getInt('rootPointsSecondary'),
                pointsPerAction: $points,
                capsPerAction: $caps,
                minimumToParticipate: $request->request->getInt('minimumToParticipate'),
                bandThresholds: [
                    $request->request->getInt('bandKnown'),
                    $request->request->getInt('bandTrusted'),
                    $request->request->getInt('bandHighly'),
                ],
            ));
        } catch (InvalidArgumentException) {
            $this->addFlash('danger', 'trust.flash_invalid_config');

            return $this->redirectToRoute('app_admin_trust_context', ['context' => $context]);
        }

        $this->scoreProvider->invalidate($context);

        return $this->redirectToRoute('app_admin_trust_context', ['context' => $context]);
    }
}
