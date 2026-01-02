<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Entity\EmailTemplate;
use App\Form\EmailTemplateType;
use App\Repository\EmailTemplateRepository;
use App\Service\EmailService;
use App\Service\EmailTemplateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
        $templatesByIdentifier = $this->buildTemplatesByMockKey($templates);
        $mockList = $this->emailService->getMockEmailList();

        $emails = [];
        foreach ($mockList as $identifier => $mockData) {
            $dbTemplate = $templatesByIdentifier[$identifier] ?? null;
            $emails[$identifier] = [
                'subject' => $mockData['subject'],
                'context' => $mockData['context'],
                'renderedBody' => $dbTemplate
                    ? $this->templateService->renderContent($dbTemplate->getBody(), $mockData['context'])
                    : '<p>Template not found. Run app:email-templates:seed</p>',
                'template' => $dbTemplate,
            ];
        }

        return $this->render('admin/email/list.html.twig', [
            'active' => 'email',
            'emails' => $emails,
        ]);
    }

    /**
     * @param EmailTemplate[] $templates
     *
     * @return array<string, EmailTemplate>
     */
    private function buildTemplatesByMockKey(array $templates): array
    {
        $result = [];
        foreach ($templates as $template) {
            $result[$template->getIdentifier()] = $template;
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
        return $mockList[$identifier]['context'] ?? [];
    }
}
