<?php declare(strict_types=1);

namespace Module\Task\Internal\Controller;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Section\AdminCollapsibleSection;
use App\Admin\Section\Items\AdminSectionTextItem;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionForm;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Controller\Admin\Settings\AbstractSettingsController;
use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionParameter;
use Module\Task\Contract\ActionSection;
use Module\Task\Contract\Status;
use Module\Task\Internal\Action\Registry;
use Module\Task\Internal\Action\Requests;
use Module\Task\Internal\Action\Settings;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/system/maintenance')]
#[IsGranted('ROLE_ADMIN')]
final class MaintenanceController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    private const string IDENTIFIER = '[a-z0-9._\-]+';
    private const string RUN_TOKEN = 'app_admin_system_maintenance_run';
    private const string SETTINGS_TOKEN = 'app_admin_system_maintenance_settings';
    private const int HISTORY_LIMIT = 20;

    public function __construct(
        TranslatorInterface $translator,
        private readonly Registry $actions,
        private readonly Requests $requests,
        private readonly Settings $settings,
        private readonly FormFactoryInterface $forms,
    ) {
        parent::__construct($translator, 'maintenance');
    }

    #[Route('', name: 'app_admin_system_maintenance', methods: ['GET'])]
    public function list(): Response
    {
        $latest = $this->requests->latestPerAction();
        $sections = [];
        $problems = 0;
        foreach (ActionSection::cases() as $section) {
            $rows = [];
            foreach ($this->actions->all() as $identifier => $action) {
                if ($action->getSection() !== $section) {
                    continue;
                }
                $last = $latest[$identifier] ?? null;
                if ($last?->getStatus() !== null && $last->getStatus() !== Status::ok) {
                    $problems++;
                }
                $rows[] = [
                    'identifier' => $identifier,
                    'action' => $action,
                    'facts' => $action->getFacts(),
                    'last' => $last,
                    'runsFromRow' => $action->getParameters() === [],
                ];
            }
            if ($rows === []) {
                continue;
            }
            $sections[] = [
                'section' => new AdminCollapsibleSection(
                    id: 'maintenance-' . $section->value,
                    left: [new AdminSectionTextItem($this->translator->trans($section->label()), 'has-text-weight-bold')],
                    right: [new AdminSectionTextItem((string) count($rows), 'tag is-light mr-2')],
                    openByDefault: true,
                ),
                'rows' => $rows,
            ];
        }

        $adminTop = new AdminTop(info: [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($this->actions->all()),
                $this->translator->trans('admin_maintenance.summary_actions'),
            )),
            $problems === 0
                ? new AdminTopInfoHtml(sprintf(
                    '<span class="tag is-success is-medium">%s</span>',
                    $this->translator->trans('admin_maintenance.summary_all_ok'),
                ))
                : new AdminTopInfoHtml(sprintf(
                    '<span class="tag is-danger is-medium">%d&nbsp;%s</span>',
                    $problems,
                    $this->translator->trans('admin_maintenance.summary_problems'),
                )),
        ]);

        return $this->render('@Task/admin/maintenance/list.html.twig', [
            'active' => 'system',
            'sections' => $sections,
            'runToken' => self::RUN_TOKEN,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{identifier}', name: 'app_admin_system_maintenance_show', requirements: ['identifier' => self::IDENTIFIER], methods: ['GET'])]
    public function show(string $identifier): Response
    {
        $action = $this->action($identifier);

        return $this->renderShow($action, $this->runForm($action), $this->settingsForm($action));
    }

    #[Route('/{identifier}/run', name: 'app_admin_system_maintenance_run', requirements: ['identifier' => self::IDENTIFIER], methods: ['POST'])]
    public function run(string $identifier, Request $request): Response
    {
        $action = $this->action($identifier);
        $form = $this->runForm($action);
        if ($form === null) {
            if (!$this->isCsrfTokenValid(self::RUN_TOKEN . $identifier, (string) $request->request->get('_token'))) {
                throw new BadRequestHttpException('Invalid CSRF token.');
            }
            $this->queue($action, []);

            return (
                $request->query->getString('return') === 'show'
                    ? $this->redirectToRoute('app_admin_system_maintenance_show', ['identifier' => $identifier])
                    : $this->redirectToRoute('app_admin_system_maintenance')
            );
        }

        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderShow($action, $form, $this->settingsForm($action));
        }
        $this->queue($action, $this->values($action, $form));

        return $this->redirectToRoute('app_admin_system_maintenance_show', ['identifier' => $identifier]);
    }

    #[Route('/{identifier}/settings', name: 'app_admin_system_maintenance_settings', requirements: ['identifier' => self::IDENTIFIER], methods: ['POST'])]
    public function saveSettings(string $identifier, Request $request): Response
    {
        $action = $this->action($identifier);
        $form = $this->settingsForm($action);
        if ($form === null) {
            throw $this->createNotFoundException();
        }

        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderShow($action, $this->runForm($action), $form);
        }
        $this->settings->remember($action, $this->values($action, $form));

        return $this->redirectToRoute('app_admin_system_maintenance_show', ['identifier' => $identifier]);
    }

    private function action(string $identifier): ActionInterface
    {
        return $this->actions->find($identifier) ?? throw $this->createNotFoundException();
    }

    /**
     * @param array<string, string|int|bool|null> $values
     */
    private function queue(ActionInterface $action, array $values): void
    {
        $this->requests->submit($action, $values, $this->getUser()?->getUserIdentifier());
        $this->addFlash('success', $this->translator->trans('admin_maintenance.flash_queued', ['%action%' => $this->translator->trans($action->getLabel())]));
    }

    private function renderShow(ActionInterface $action, ?FormInterface $runForm, ?FormInterface $settingsForm): Response
    {
        $identifier = $action->getIdentifier();
        $history = $this->requests->recent($identifier, self::HISTORY_LIMIT);
        $last = $history[0] ?? null;

        $info = [new AdminTopInfoHtml(sprintf('<strong>%s</strong>', htmlspecialchars($this->translator->trans($action->getLabel()))))];
        if ($last !== null) {
            $info[] = $last->getStatus() === null
                ? new AdminTopInfoHtml(sprintf(
                    '<span class="tag %s is-medium">%s</span>',
                    $last->getState()->tagVariant(),
                    $this->translator->trans($last->getState()->label()),
                ))
                : new AdminTopInfoHtml(sprintf(
                    '<span class="tag %s is-medium">%s</span>',
                    $last->getStatus()->tagVariant(),
                    $this->translator->trans($last->getStatus()->label()),
                ));
        }

        $actions = [];
        if ($runForm === null) {
            $actions[] = new AdminTopActionForm(
                label: $this->translator->trans('admin_maintenance.action_run'),
                target: $this->generateUrl('app_admin_system_maintenance_run', ['identifier' => $identifier, 'return' => 'show']),
                csrfTokenId: self::RUN_TOKEN . $identifier,
                icon: 'play',
                variant: 'is-warning',
            );
        }
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('global.button_back'),
            target: $this->generateUrl('app_admin_system_maintenance'),
            icon: 'arrow-left',
        );

        return $this->render(
            '@Task/admin/maintenance/show.html.twig',
            [
                'active' => 'system',
                'action' => $action,
                'facts' => $action->getFacts(),
                'runForm' => $runForm?->createView(),
                'settingsForm' => $settingsForm?->createView(),
                'history' => $history,
                'adminTop' => new AdminTop(info: $info, actions: $actions),
                'adminTabs' => $this->getTabs(),
            ],
            new Response(status: $this->hasInvalidForm($runForm, $settingsForm) ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK),
        );
    }

    private function hasInvalidForm(?FormInterface ...$forms): bool
    {
        foreach ($forms as $form) {
            if ($form !== null && $form->isSubmitted() && !$form->isValid()) {
                return true;
            }
        }

        return false;
    }

    private function runForm(ActionInterface $action): ?FormInterface
    {
        $parameters = $action->getParameters();
        if ($parameters === []) {
            return null;
        }

        return $this->form('run', $action, $parameters, self::RUN_TOKEN, 'app_admin_system_maintenance_run');
    }

    private function settingsForm(ActionInterface $action): ?FormInterface
    {
        $remembered = array_values(array_filter($action->getParameters(), static fn(ActionParameter $parameter): bool => $parameter->remembered));
        if ($remembered === []) {
            return null;
        }

        return $this->form('settings', $action, $remembered, self::SETTINGS_TOKEN, 'app_admin_system_maintenance_settings');
    }

    /**
     * @param list<ActionParameter> $parameters
     */
    private function form(string $name, ActionInterface $action, array $parameters, string $csrfIntention, string $route): FormInterface
    {
        $identifier = $action->getIdentifier();
        $prefill = $this->settings->prefill($action);
        $builder = $this->forms->createNamedBuilder($name, FormType::class, null, [
            'action' => $this->generateUrl($route, ['identifier' => $identifier]),
            'csrf_token_id' => $csrfIntention . $identifier,
        ]);
        foreach ($parameters as $parameter) {
            [$type, $options] = match ($parameter->kind) {
                ActionParameter::CHOICE => [ChoiceType::class, ['choices' => $parameter->choices, 'placeholder' => false]],
                ActionParameter::NUMBER => [IntegerType::class, []],
                ActionParameter::FLAG => [CheckboxType::class, []],
                default => [TextType::class, []],
            };
            $value = $prefill[$parameter->key] ?? null;
            $builder->add($parameter->key, $type, [
                'label' => $parameter->label,
                'required' => false,
                'data' => $parameter->kind === ActionParameter::FLAG ? (bool) $value : $value,
                ...$options,
            ]);
        }

        return $builder->getForm();
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    private function values(ActionInterface $action, FormInterface $form): array
    {
        $values = [];
        foreach ($action->getParameters() as $parameter) {
            if (!$form->has($parameter->key)) {
                continue;
            }
            $value = $form->get($parameter->key)->getData();
            $values[$parameter->key] = is_string($value) || is_int($value) || is_bool($value) ? $value : null;
        }

        return $values;
    }
}
