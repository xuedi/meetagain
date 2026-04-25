<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
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
final class DebuggingController extends AbstractAdminController
{
    /**
     * @param iterable<EmailInterface> $emailTypes
     */
    public function __construct(
        #[AutowireIterator(EmailInterface::class)]
        private readonly iterable $emailTypes,
        private readonly EmailTemplateService $templateService,
        private readonly MailerInterface $mailer,
        private readonly ConfigService $config,
        private readonly LanguageService $languageService,
        private readonly TranslatorInterface $translator,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    #[Route('', name: 'app_admin_email_debugging')]
    public function debugging(): Response
    {
        $mockData = [];
        foreach ($this->emailTypes as $emailType) {
            $data = $emailType->getDisplayMockData();
            $context = $data['context'];
            if (!array_key_exists('greeting', $context)) {
                $context['greeting'] = '';
            }
            $mockData[$emailType->getIdentifier()] = [
                'subject' => $data['subject'],
                'context' => $context,
            ];
        }

        return $this->render('admin/email/debugging/index.html.twig', [
            'active' => 'email',
            'emailTypes' => EmailType::cases(),
            'mockData' => $mockData,
            'languages' => $this->languageService->getAdminFilteredEnabledCodes(),
            'defaultType' => EmailType::cases()[0]->value,
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

        return $this->redirectToRoute('app_admin_email_debugging');
    }
}
