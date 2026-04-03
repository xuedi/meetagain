<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Controller\Admin\AbstractAdminController;
use App\Controller\Admin\AdminNavigationConfig;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Service\Email\EmailService;
use App\Service\Email\EmailTemplateService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/debugging')]
final class DebuggingController extends AbstractAdminController
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly EmailTemplateService $templateService,
        private readonly MailerInterface $mailer,
        private readonly ConfigService $config,
        private readonly LanguageService $languageService,
    ) {}

    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    #[Route('', name: 'app_admin_email_debugging')]
    public function debugging(): Response
    {
        $mockData = $this->emailService->getMockEmailList();

        foreach ($mockData as $type => $data) {
            if (array_key_exists('greeting', $mockData[$type]['context'])) {
                continue;
            }

            $mockData[$type]['context']['greeting'] = '';
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

            $this->addFlash('success', sprintf('Test email "%s" sent to %s', $subject, $recipient));
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('error', 'Failed to send email: ' . $e->getMessage());
        } catch (Throwable $e) {
            $this->addFlash('error', 'Error: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_email_debugging');
    }
}
