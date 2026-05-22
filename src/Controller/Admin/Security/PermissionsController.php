<?php declare(strict_types=1);

namespace App\Controller\Admin\Security;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Section\AdminCollapsibleSection;
use App\Admin\Section\Items\AdminSectionTextItem;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Admin\PermissionInspectorService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class PermissionsController extends AbstractSecurityController implements
    AdminNavigationInterface,
    AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly PermissionInspectorService $inspector,
    ) {
        parent::__construct($translator, 'permissions');
    }

    #[Route('/admin/security/permissions', name: 'app_admin_security_permissions', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SECURITY_PERMISSIONS_READ);

        $groups = $this->inspector->getEntriesGroupedByRole();
        $roleOrder = $this->inspector->getRoleDisplayOrder();

        $sections = [];
        foreach ($roleOrder as $index => $roleId) {
            if (!isset($groups[$roleId])) {
                continue;
            }
            $entries = $groups[$roleId];
            $hintKey = $roleId === 'Anonymous'
                ? 'admin_security_permissions.anon_no_auth'
                : 'admin_security_permissions.anon_min_role';

            $sections[] = [
                'role' => $roleId,
                'entries' => $entries,
                'section' => new AdminCollapsibleSection(
                    id: 'perm-section-' . $index,
                    left: [
                        new AdminSectionTextItem($roleId),
                        new AdminSectionTextItem($this->translator->trans($hintKey), 'has-text-grey is-size-7 ml-3'),
                    ],
                    right: [
                        new AdminSectionTextItem($this->translator->trans('admin_security_permissions.routes_count', [
                            '%count%' => count($entries),
                        ]), 'has-text-grey is-size-7 mr-3 is-nowrap'),
                    ],
                    openByDefault: false,
                ),
            ];
        }

        $adminTop = new AdminTop(info: [new AdminTopInfoText($this->translator->trans(
            'admin_security_permissions.help',
        ))]);

        return $this->render('admin/security/permissions/index.html.twig', [
            'active' => 'security',
            'sections' => $sections,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/admin/system/permissions', name: 'app_admin_system_permissions', methods: ['GET'])]
    public function legacyRedirect(): RedirectResponse
    {
        return $this->redirectToRoute('app_admin_security_permissions', [], 301);
    }
}
