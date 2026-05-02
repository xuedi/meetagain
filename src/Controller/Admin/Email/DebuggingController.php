<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Emails\EmailInterface;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Service\Email\EmailTemplateService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/debugging')]
final class DebuggingController extends AbstractEmailController implements AdminTabsInterface
{
    /**
     * @param iterable<EmailInterface> $emailTypes
     */
    public function __construct(
        TranslatorInterface $translator,
        #[AutowireIterator(EmailInterface::class)]
        private readonly iterable $emailTypes,
        private readonly EmailTemplateService $templateService,
        private readonly MailerInterface $mailer,
        private readonly ConfigService $config,
        private readonly LanguageService $languageService,
    ) {
        parent::__construct($translator, 'debugging');
    }

    #[Route('', name: 'app_admin_email_debugging')]
    public function debugging(Request $request): Response
    {
        $languages = $this->languageService->getAdminFilteredEnabledCodes();
        $defaultType = EmailType::cases()[0];
        $defaultLanguage = $languages[0];

        $typeValue = $request->query->getString('type');
        $currentType = $typeValue !== '' ? (EmailType::tryFrom($typeValue) ?? $defaultType) : $defaultType;

        $langValue = $request->query->getString('lang');
        $currentLanguage = in_array($langValue, $languages, true) ? $langValue : $defaultLanguage;

        $context = $this->resolveMockContext($currentType);

        $adminTop = new AdminTop(
            info: [new AdminTopInfoText($this->translator->trans('admin_email_debugging.intro'))],
            actions: [
                $this->buildTypeDropdown($currentType, $currentLanguage),
                $this->buildLanguageDropdown($currentLanguage, $currentType, $languages),
            ],
        );

        return $this->render('admin/email/debugging/index.html.twig', [
            'active' => 'email',
            'currentType' => $currentType->value,
            'currentLanguage' => $currentLanguage,
            'context' => $context,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/send', name: 'app_admin_email_debugging_send', methods: ['POST'])]
    public function send(Request $request): Response
    {
        $emailTypeValue = $request->request->getString('emailType');
        $recipient = $request->request->getString('recipient');
        $language = $request->request->getString('language');
        $context = $request->request->all('context');

        try {
            $type = EmailType::from($emailTypeValue);
            $templateContent = $this->templateService->getTemplateContent($type, $language);
            $subject = $this->templateService->renderContent($templateContent['subject'], $context);
            $body = $this->templateService->renderContent($templateContent['body'], $context);

            $email = new Email()
                ->from($this->config->getMailerAddress())
                ->to($recipient)
                ->subject($subject)
                ->html($body);

            $this->mailer->send($email);

            $this->addFlash('success', $this->translator->trans('admin_email_debugging.flash_sent', ['%subject%' => $subject, '%recipient%' => $recipient]));
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('error', $this->translator->trans('admin_email_debugging.flash_failed'));
        } catch (Throwable $e) {
            $this->addFlash('error', $this->translator->trans('admin_email_debugging.flash_error'));
        }

        return $this->redirectToRoute('app_admin_email_debugging', [
            'type' => $emailTypeValue,
            'lang' => $language,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMockContext(EmailType $type): array
    {
        foreach ($this->emailTypes as $emailType) {
            if ($emailType->getIdentifier() !== $type->value) {
                continue;
            }
            $context = $emailType->getDisplayMockData()['context'];
            if (!array_key_exists('greeting', $context)) {
                $context['greeting'] = '';
            }

            return $context;
        }

        return [];
    }

    private function buildTypeDropdown(EmailType $current, string $language): AdminTopActionDropdown
    {
        $options = [];
        foreach (EmailType::cases() as $type) {
            $options[] = new AdminTopActionDropdownOption(
                label: $this->humanize($type->value),
                target: $this->generateUrl('app_admin_email_debugging', ['type' => $type->value, 'lang' => $language]),
                isActive: $type === $current,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_email_debugging.field_email_type') . ':',
                $this->humanize($current->value),
            ),
            options: $options,
            icon: 'envelope',
        );
    }

    /**
     * @param list<string> $languages
     */
    private function buildLanguageDropdown(string $current, EmailType $type, array $languages): AdminTopActionDropdown
    {
        $options = [];
        foreach ($languages as $code) {
            $options[] = new AdminTopActionDropdownOption(
                label: $code,
                target: $this->generateUrl('app_admin_email_debugging', ['type' => $type->value, 'lang' => $code]),
                isActive: $code === $current,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_email_debugging.field_language') . ':',
                $current,
            ),
            options: $options,
            icon: 'language',
        );
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
