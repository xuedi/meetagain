<?php

declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
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
final class BlocklistController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly EmailBlocklistRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_admin_email_blocklist')]
    public function list(): Response
    {
        return $this->render('admin/email/blocklist/list.html.twig', [
            'active' => 'email',
            'entries' => $this->repo->findAllOrdered(),
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
                $this->addFlash('warning', $this->translator->trans('admin_email.flash_warning_already_blocklisted', [
                    '%email%' => $existing->getEmail(),
                    '%reason%' => $existing->getReason(),
                ]));

                return $this->render('admin/email/blocklist/add.html.twig', [
                    'active' => 'email',
                    'form' => $form,
                ]);
            }

            /** @var User|null $currentUser */
            $currentUser = $this->getUser();
            $entry->setAddedBy($currentUser instanceof User ? $currentUser : null);
            $entry->setAddedAt(new DateTimeImmutable());

            $this->em->persist($entry);
            $this->em->flush();

            $this->addFlash('success', $this->translator->trans('admin_email.flash_success_added_blocklist', [
                '%email%' => $entry->getEmail(),
            ]));

            return $this->redirectToRoute('app_admin_email_blocklist');
        }

        return $this->render('admin/email/blocklist/add.html.twig', [
            'active' => 'email',
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_email_blocklist_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function delete(EmailBlocklistEntry $entry): Response
    {
        $removed = $entry->getEmail();
        $this->em->remove($entry);
        $this->em->flush();

        $this->addFlash('success', $this->translator->trans('admin_email.flash_success_removed_blocklist', [
            '%email%' => $removed,
        ]));

        return $this->redirectToRoute('app_admin_email_blocklist');
    }
}
