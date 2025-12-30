<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EmailTemplate;
use App\Form\EmailTemplateType;
use App\Repository\EmailTemplateRepository;
use App\Service\EmailService;
use App\Service\EmailTemplateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminEmailController extends AbstractController
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly EmailTemplateRepository $templateRepo,
        private readonly EmailTemplateService $templateService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/admin/email/', name: 'app_admin_email')]
    public function list(): Response
    {
        $templates = $this->templateRepo->findAll();
        $templatesByMockKey = $this->buildTemplatesByMockKey($templates);

        return $this->render('admin/email/list.html.twig', [
            'active' => 'email',
            'emails' => $this->emailService->getMockEmailList(),
            'templatesByMockKey' => $templatesByMockKey,
        ]);
    }

    /**
     * @param EmailTemplate[] $templates
     *
     * @return array<string, EmailTemplate>
     */
    private function buildTemplatesByMockKey(array $templates): array
    {
        $mapping = [
            'verification_request' => 'email_verification_request',
            'welcome' => 'email_welcome',
            'password_reset_request' => 'email_password_reset_request',
            'notification_message' => 'email_message_notification',
            'notification_rsvp' => 'email_rsvp_notification',
            'notification_event_canceled' => 'email_event_canceled',
        ];

        $result = [];
        foreach ($templates as $template) {
            $mockKey = $mapping[$template->getIdentifier()] ?? null;
            if ($mockKey !== null) {
                $result[$mockKey] = $template;
            }
        }

        return $result;
    }

    #[Route('/admin/email/{id}/edit', name: 'app_admin_email_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EmailTemplate $template): Response
    {
        $form = $this->createForm(EmailTemplateType::class, $template);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $template->setUpdatedAt(new DateTimeImmutable());
            $this->em->flush();

            $this->addFlash('success', 'Email template updated successfully.');

            return $this->redirectToRoute('app_admin_email');
        }

        return $this->render('admin/email/edit.html.twig', [
            'active' => 'email',
            'form' => $form,
            'template' => $template,
        ]);
    }

    #[Route('/admin/email/{id}/preview', name: 'app_admin_email_preview')]
    public function preview(EmailTemplate $template): Response
    {
        $mockList = $this->emailService->getMockEmailList();
        $mockContext = $this->getMockContextForTemplate($template->getIdentifier(), $mockList);

        $renderedSubject = $this->templateService->renderContent(
            $template->getSubject(),
            $mockContext
        );
        $renderedBody = $this->templateService->renderContent(
            $template->getBody(),
            $mockContext
        );

        return $this->render('admin/email/preview.html.twig', [
            'active' => 'email',
            'template' => $template,
            'renderedSubject' => $renderedSubject,
            'renderedBody' => $renderedBody,
            'context' => $mockContext,
        ]);
    }

    #[Route('/admin/email/{id}/reset', name: 'app_admin_email_reset', methods: ['POST'])]
    public function reset(EmailTemplate $template): Response
    {
        $defaults = $this->templateService->getDefaultTemplates();
        $identifier = $template->getIdentifier();

        if (isset($defaults[$identifier])) {
            $template->setSubject($defaults[$identifier]['subject']);
            $template->setBody($defaults[$identifier]['body']);
            $template->setUpdatedAt(new DateTimeImmutable());
            $this->em->flush();

            $this->addFlash('success', 'Email template reset to default.');
        }

        return $this->redirectToRoute('app_admin_email_edit', ['id' => $template->getId()]);
    }

    private function getMockContextForTemplate(string $identifier, array $mockList): array
    {
        $mapping = [
            'verification_request' => 'email_verification_request',
            'welcome' => 'email_welcome',
            'password_reset_request' => 'email_password_reset_request',
            'notification_message' => 'email_message_notification',
            'notification_rsvp' => 'email_rsvp_notification',
            'notification_event_canceled' => 'email_event_canceled',
        ];

        $mockKey = $mapping[$identifier] ?? null;
        if ($mockKey !== null && isset($mockList[$mockKey])) {
            return $mockList[$mockKey]['context'];
        }

        return [];
    }
}
