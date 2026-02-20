<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Entity\AdminLink;
use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateTranslation;
use App\Form\EmailTemplateType;
use App\Repository\EmailTemplateRepository;
use App\Repository\EmailTemplateTranslationRepository;
use App\Service\EmailService;
use App\Service\EmailTemplateService;
use App\Service\TranslationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/templates')]
class TemplatesController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly EmailService $emailService,
        private readonly EmailTemplateRepository $templateRepo,
        private readonly EmailTemplateService $templateService,
        private readonly EntityManagerInterface $em,
        private readonly TranslationService $translationService,
        private readonly EmailTemplateTranslationRepository $translationRepo,
    ) {}

    #[Route('', name: 'app_admin_email_templates')]
    public function templates(): Response
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
                    ? $this->templateService->renderContent(
                        $dbTemplate->getBody($this->translationService->getLanguageCodes()[0]),
                        $mockData['context'],
                    )
                    : '<p>Template not found. Run app:email-templates:seed</p>',
                'template' => $dbTemplate,
            ];
        }

        return $this->render('admin/email/templates/list.html.twig', [
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

    #[Route('/{id}/edit', name: 'app_admin_email_templates_edit', methods: ['GET', 'POST'])]
    public function templatesEdit(Request $request, EmailTemplate $template): Response
    {
        $form = $this->createForm(EmailTemplateType::class, $template);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Save translations for each language
            foreach ($this->translationService->getLanguageCodes() as $languageCode) {
                $translation = $this->getOrCreateTranslation($languageCode, $template->getId());
                $translation->setEmailTemplate($template);
                $translation->setLanguage($languageCode);
                $translation->setSubject($form->get("subject-$languageCode")->getData());
                $translation->setBody($form->get("body-$languageCode")->getData());
                $translation->setUpdatedAt(new DateTimeImmutable());

                $this->em->persist($translation);
            }

            $template->setUpdatedAt(new DateTimeImmutable());
            $this->em->flush();

            $this->addFlash('success', 'Email template updated successfully.');

            return $this->redirectToRoute('app_admin_email_templates');
        }

        return $this->render('admin/email/templates/edit.html.twig', [
            'active' => 'email',
            'form' => $form,
            'template' => $template,
            'languages' => $this->translationService->getLanguageCodes(),
        ]);
    }

    #[Route('/{id}/preview', name: 'app_admin_email_templates_preview')]
    public function templatesPreview(Request $request, EmailTemplate $template): Response
    {
        $language = $request->query->getString('lang', $this->translationService->getLanguageCodes()[0]);
        $mockList = $this->emailService->getMockEmailList();
        $mockContext = $this->getMockContextForTemplate($template->getIdentifier(), $mockList);

        $renderedSubject = $this->templateService->renderContent($template->getSubject($language), $mockContext);
        $renderedBody = $this->templateService->renderContent($template->getBody($language), $mockContext);

        return $this->render('admin/email/templates/preview.html.twig', [
            'active' => 'email',
            'template' => $template,
            'renderedSubject' => $renderedSubject,
            'renderedBody' => $renderedBody,
            'context' => $mockContext,
            'currentLanguage' => $language,
            'languages' => $this->translationService->getLanguageCodes(),
        ]);
    }

    #[Route('/{id}/reset', name: 'app_admin_email_templates_reset', methods: ['POST'])]
    public function templatesReset(EmailTemplate $template): Response
    {
        $identifier = $template->getIdentifier();

        // Reset translations for all enabled languages with language-specific defaults
        foreach ($this->translationService->getLanguageCodes() as $languageCode) {
            $langDefaults = $this->templateService->getDefaultTemplates($languageCode);

            if (!isset($langDefaults[$identifier])) {
                continue;
            }

            $translation = $this->getOrCreateTranslation($languageCode, $template->getId());
            $translation->setEmailTemplate($template);
            $translation->setLanguage($languageCode);
            $translation->setSubject($langDefaults[$identifier]['subject']);
            $translation->setBody($langDefaults[$identifier]['body']);
            $translation->setUpdatedAt(new DateTimeImmutable());

            $this->em->persist($translation);
        }

        $template->setUpdatedAt(new DateTimeImmutable());
        $this->em->flush();

        $this->addFlash('success', 'Email template reset to default for all languages.');

        return $this->redirectToRoute('app_admin_email_templates_edit', ['id' => $template->getId()]);
    }

    private function getMockContextForTemplate(string $identifier, array $mockList): array
    {
        return $mockList[$identifier]['context'] ?? [];
    }

    private function getOrCreateTranslation(string $languageCode, ?int $templateId): EmailTemplateTranslation
    {
        $translation = $this->translationRepo->findOneBy([
            'language' => $languageCode,
            'emailTemplate' => $templateId,
        ]);

        if ($translation !== null) {
            return $translation;
        }

        return new EmailTemplateTranslation();
    }
}
