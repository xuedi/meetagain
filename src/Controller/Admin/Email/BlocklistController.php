<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\EmailBlocklistEntry;
use App\Entity\User;
use App\Form\EmailBlocklistType;
use App\Repository\EmailBlocklistRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/blocklist')]
final class BlocklistController extends AbstractEmailController implements AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly EmailBlocklistRepository $repo,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($translator, 'blocklist');
    }

    #[Route('', name: 'app_admin_email_blocklist')]
    public function list(): Response
    {
        $entries = $this->repo->findAllOrdered();

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%d</strong>&nbsp;%s',
                    count($entries),
                    $this->translator->trans('admin_email_blocklist.summary_total'),
                )),
            ],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_email_blocklist.button_add'),
                    target: $this->generateUrl('app_admin_email_blocklist_add'),
                    icon: 'plus',
                ),
            ],
        );

        return $this->render('admin/email/blocklist/list.html.twig', [
            'active' => 'email',
            'entries' => $entries,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/add', name: 'app_admin_email_blocklist_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $entry = new EmailBlocklistEntry();
        $form = $this->createForm(EmailBlocklistType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existing = $this->repo->findByEmail((string) $entry->getEmail());
            if ($existing !== null) {
                $this->addFlash('warning', $this->translator->trans('admin_email_blocklist.flash_already_blocklisted', [
                    '%email%' => $existing->getEmail(),
                    '%reason%' => $existing->getReason(),
                ]));

                return $this->render('admin/email/blocklist/add.html.twig', [
                    'active' => 'email',
                    'form' => $form,
                    'adminTop' => $this->buildAddAdminTop(),
                    'adminTabs' => $this->getTabs(),
                ]);
            }

            /** @var User|null $currentUser */
            $currentUser = $this->getUser();
            $entry->setAddedBy($currentUser instanceof User ? $currentUser : null);
            $entry->setAddedAt(new DateTimeImmutable());

            $this->em->persist($entry);
            $this->em->flush();

            $this->addFlash('success', $this->translator->trans('admin_email_blocklist.flash_added', [
                '%email%' => $entry->getEmail(),
            ]));

            return $this->redirectToRoute('app_admin_email_blocklist');
        }

        return $this->render('admin/email/blocklist/add.html.twig', [
            'active' => 'email',
            'form' => $form,
            'adminTop' => $this->buildAddAdminTop(),
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_email_blocklist_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function delete(EmailBlocklistEntry $entry): Response
    {
        $removed = $entry->getEmail();
        $this->em->remove($entry);
        $this->em->flush();

        $this->addFlash('success', $this->translator->trans('admin_email_blocklist.flash_removed', [
            '%email%' => $removed,
        ]));

        return $this->redirectToRoute('app_admin_email_blocklist');
    }

    private function buildAddAdminTop(): AdminTop
    {
        return new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%s</strong>',
                    htmlspecialchars($this->translator->trans('admin_email_blocklist.add_title'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                )),
            ],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('global.button_back'),
                    target: $this->generateUrl('app_admin_email_blocklist'),
                    icon: 'arrow-left',
                ),
            ],
        );
    }
}
