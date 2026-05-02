<?php

declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Admin\Section\AdminCollapsibleSection;
use App\Admin\Section\Items\AdminSectionLinkItem;
use App\Admin\Section\Items\AdminSectionTextItem;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Emails\EmailInterface;
use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateTranslation;
use App\Form\EmailTemplateType;
use App\Repository\EmailTemplateRepository;
use App\Repository\EmailTemplateTranslationRepository;
use App\Service\Config\LanguageService;
use App\Service\Email\EmailTemplateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/templates')]
final class TemplatesController extends AbstractEmailController implements AdminNavigationInterface, AdminTabsInterface
{
    /**
     * @param iterable<EmailInterface> $emailTypes
     */
    public function __construct(
        TranslatorInterface $translator,
        #[AutowireIterator(EmailInterface::class)]
        private readonly iterable $emailTypes,
        private readonly EmailTemplateRepository $templateRepo,
        private readonly EmailTemplateService $templateService,
        private readonly EntityManagerInterface $em,
        private readonly LanguageService $languageService,
        private readonly EmailTemplateTranslationRepository $translationRepo,
    ) {
        parent::__construct($translator, 'templates');
    }

    #[Route('', name: 'app_admin_email_templates')]
    public function templates(): Response
    {
        $templates = $this->templateRepo->findAll();
        $templatesByIdentifier = $this->buildTemplatesByMockKey($templates);

        $emails = [];
        foreach ($this->emailTypes as $emailType) {
            $identifier = $emailType->getIdentifier();
            $mockData = $emailType->getDisplayMockData();
            $dbTemplate = $templatesByIdentifier[$identifier] ?? null;
            $right = $dbTemplate !== null
                ? [new AdminSectionLinkItem(
                    href: $this->generateUrl('app_admin_email_templates_edit', ['id' => $dbTemplate->getId()]),
                    icon: 'edit',
                    title: $this->translator->trans('admin_email_templates.button_preview'),
                )]
                : [];
            $emails[$identifier] = [
                'subject' => $mockData['subject'],
                'context' => $mockData['context'],
                'renderedBody' => $dbTemplate
                    ? $this->templateService->renderContent(
                        $dbTemplate->getBody($this->languageService->getAdminFilteredEnabledCodes()[0]),
                        $mockData['context'],
                    )
                    : '<p>Template not found. Run app:email-templates:seed</p>',
                'template' => $dbTemplate,
                'section' => new AdminCollapsibleSection(
                    id: 'email-section-' . $identifier,
                    left: [
                        new AdminSectionTextItem($this->translator->trans($identifier)),
                        new AdminSectionTextItem(
                            $this->translator->trans($emailType->getTriggerLabel()),
                            'has-text-grey is-size-7 ml-3',
                        ),
                    ],
                    right: $right,
                    openByDefault: false,
                ),
            ];
        }

        $adminTop = new AdminTop(
            info: [new AdminTopInfoText($this->translator->trans('admin_email_templates.intro'))],
        );

        return $this->render('admin/email/templates/list.html.twig', [
            'active' => 'email',
            'emails' => $emails,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
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
            foreach ($this->languageService->getAdminFilteredEnabledCodes() as $languageCode) {
                $translation = $this->getOrCreateTranslation($languageCode, $template->getId());
                $translation->setEmailTemplate($template);
                $translation->setLanguage($languageCode);
                $translation->setSubject($form->get("subject-{$languageCode}")->getData());
                $translation->setBody($form->get("body-{$languageCode}")->getData());
                $translation->setUpdatedAt(new DateTimeImmutable());

                $this->em->persist($translation);
            }

            $template->setUpdatedAt(new DateTimeImmutable());
            $this->em->flush();

            $this->addFlash('success', $this->translator->trans('admin_email_templates.flash_saved'));

            return $this->redirectToRoute('app_admin_email_templates_edit', ['id' => $template->getId()]);
        }

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%s</strong>',
                    htmlspecialchars((string) $template->getIdentifier(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                )),
            ],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_email_templates.button_reset'),
                    target: $this->generateUrl('app_admin_email_templates_reset', ['id' => $template->getId()]),
                    icon: 'rotate-left',
                    variant: 'is-warning',
                    confirm: $this->translator->trans('admin_email_templates.confirm_reset'),
                ),
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_email_templates.button_preview'),
                    target: $this->generateUrl('app_admin_email_templates_preview', ['id' => $template->getId()]),
                    icon: 'eye',
                ),
                new AdminTopActionButton(
                    label: $this->translator->trans('global.button_back'),
                    target: $this->generateUrl('app_admin_email_templates'),
                    icon: 'arrow-left',
                ),
            ],
        );

        return $this->render('admin/email/templates/edit.html.twig', [
            'active' => 'email',
            'form' => $form,
            'template' => $template,
            'languages' => $this->languageService->getAdminFilteredEnabledCodes(),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}/preview', name: 'app_admin_email_templates_preview')]
    public function templatesPreview(Request $request, EmailTemplate $template): Response
    {
        $language = $request->query->getString('lang', $this->languageService->getAdminFilteredEnabledCodes()[0]);
        $mockContext = $this->getMockContextForTemplate($template->getIdentifier());

        $renderedSubject = $this->templateService->renderContent($template->getSubject($language), $mockContext);
        $renderedBody = $this->templateService->renderContent($template->getBody($language), $mockContext);

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%s</strong>',
                    htmlspecialchars((string) $template->getIdentifier(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                )),
                new AdminTopInfoText($this->translator->trans('admin_email_templates.preview_mock_notice')),
            ],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('global.button_back'),
                    target: $this->generateUrl('app_admin_email_templates_edit', ['id' => $template->getId()]),
                    icon: 'arrow-left',
                ),
            ],
        );

        return $this->render('admin/email/templates/preview.html.twig', [
            'active' => 'email',
            'template' => $template,
            'renderedSubject' => $renderedSubject,
            'renderedBody' => $renderedBody,
            'context' => $mockContext,
            'currentLanguage' => $language,
            'languages' => $this->languageService->getAdminFilteredEnabledCodes(),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}/reset', name: 'app_admin_email_templates_reset', methods: ['GET', 'POST'])]
    public function templatesReset(EmailTemplate $template): Response
    {
        $identifier = $template->getIdentifier();

        // Reset translations for all enabled languages with language-specific defaults
        foreach ($this->languageService->getAdminFilteredEnabledCodes() as $languageCode) {
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

        $this->addFlash('success', $this->translator->trans('admin_email_templates.flash_reset'));

        return $this->redirectToRoute('app_admin_email_templates_edit', ['id' => $template->getId()]);
    }

    private function getMockContextForTemplate(string $identifier): array
    {
        foreach ($this->emailTypes as $emailType) {
            if ($emailType->getIdentifier() === $identifier) {
                return $emailType->getDisplayMockData()['context'];
            }
        }

        return [];
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
