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
                $this->addFlash('warning', sprintf(
                    '"%s" is already on the blocklist (reason: %s).',
                    $existing->getEmail(),
                    $existing->getReason(),
                ));

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

            $this->addFlash('success', sprintf('Added "%s" to the blocklist.', $entry->getEmail()));

            return $this->redirectToRoute('app_admin_email_blocklist');
        }

        return $this->render('admin/email/blocklist/add.html.twig', [
            'active' => 'email',
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_email_blocklist_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(EmailBlocklistEntry $entry, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('blocklist_delete_' . $entry->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $removed = $entry->getEmail();
        $this->em->remove($entry);
        $this->em->flush();

        $this->addFlash('success', sprintf('Removed "%s" from the blocklist.', $removed));

        return $this->redirectToRoute('app_admin_email_blocklist');
    }
}
