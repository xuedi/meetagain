<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
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

class AdminEmailController extends AbstractController
{
    private const string DEFAULT_LANGUAGE = 'en';

    public function __construct(
        private readonly EmailService $emailService,
        private readonly EmailTemplateRepository $templateRepo,
        private readonly EmailTemplateService $templateService,
        private readonly EntityManagerInterface $em,
        private readonly TranslationService $translationService,
        private readonly EmailTemplateTranslationRepository $translationRepo,
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
                    ? $this->templateService->renderContent($dbTemplate->getBody(self::DEFAULT_LANGUAGE), $mockData['context'])
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

            return $this->redirectToRoute('app_admin_email');
        }

        return $this->render('admin/email/edit.html.twig', [
            'active' => 'email',
            'form' => $form,
            'template' => $template,
            'languages' => $this->translationService->getLanguageCodes(),
        ]);
    }

    #[Route('/admin/email/{id}/preview', name: 'app_admin_email_preview')]
    public function preview(Request $request, EmailTemplate $template): Response
    {
        $language = $request->query->getString('lang', self::DEFAULT_LANGUAGE);
        $mockList = $this->emailService->getMockEmailList();
        $mockContext = $this->getMockContextForTemplate($template->getIdentifier(), $mockList);

        $renderedSubject = $this->templateService->renderContent(
            $template->getSubject($language),
            $mockContext
        );
        $renderedBody = $this->templateService->renderContent(
            $template->getBody($language),
            $mockContext
        );

        return $this->render('admin/email/preview.html.twig', [
            'active' => 'email',
            'template' => $template,
            'renderedSubject' => $renderedSubject,
            'renderedBody' => $renderedBody,
            'context' => $mockContext,
            'currentLanguage' => $language,
            'languages' => $this->translationService->getLanguageCodes(),
        ]);
    }

    #[Route('/admin/email/{id}/reset', name: 'app_admin_email_reset', methods: ['POST'])]
    public function reset(EmailTemplate $template): Response
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

        return $this->redirectToRoute('app_admin_email_edit', ['id' => $template->getId()]);
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
