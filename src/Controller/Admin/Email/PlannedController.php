<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Service\Email\PlannedEmailService;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/planned')]
final class PlannedController extends AbstractEmailController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly PlannedEmailService $plannedEmailService,
    ) {
        parent::__construct($translator, 'planned');
    }

    #[Route('', name: 'app_admin_email_planned')]
    public function list(Request $request): Response
    {
        $onlyExpected = $request->query->getBoolean('onlyExpected');

        $from = new DateTimeImmutable();
        $to = $from->modify('+14 days');
        $items = $this->plannedEmailService->getPlannedItems($from, $to);

        if ($onlyExpected) {
            $items = array_values(array_filter(
                $items,
                static fn ($item): bool => (int) $item->expectedRecipients > 0,
            ));
        }

        $modeKey = $onlyExpected
            ? 'admin_email_planned.mode_only_expected'
            : 'admin_email_planned.mode_all';

        $toggleAction = $onlyExpected
            ? new AdminTopActionButton(
                label: $this->translator->trans('admin_email_planned.filter_show_all'),
                target: $this->generateUrl('app_admin_email_planned'),
                icon: 'list',
            )
            : new AdminTopActionButton(
                label: $this->translator->trans('admin_email_planned.filter_only_expected'),
                target: $this->generateUrl('app_admin_email_planned', ['onlyExpected' => 1]),
                icon: 'filter',
            );

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%d</strong>&nbsp;%s',
                    count($items),
                    $this->translator->trans('admin_email_planned.summary_planned'),
                )),
                new AdminTopInfoText($this->translator->trans($modeKey)),
            ],
            actions: [$toggleAction],
        );

        return $this->render('admin/email/planned/list.html.twig', [
            'active' => 'email',
            'activeSection' => 'planned',
            'items' => $items,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }
}
