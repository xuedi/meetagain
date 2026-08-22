<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Emails\EmailInterface;
use App\Emails\EmailQueueInterface;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Service\Email\BlocklistCheckerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/debugging')]
final class DebuggingController extends AbstractEmailController implements AdminNavigationInterface, AdminTabsInterface
{
    /**
     * @param iterable<EmailInterface> $emailTypes
     */
    public function __construct(
        TranslatorInterface $translator,
        #[AutowireIterator(EmailInterface::class)]
        private readonly iterable $emailTypes,
        private readonly EmailQueueInterface $emailQueue,
        private readonly BlocklistCheckerInterface $blocklist,
        private readonly ConfigService $config,
        private readonly LanguageService $languageService,
    ) {
        parent::__construct($translator, 'debugging');
    }

    #[Route('', name: 'app_admin_email_debugging')]
    public function debugging(Request $request): Response
    {
        $languages = $this->languageService->getAdminFilteredEnabledCodes();
        $identifiers = $this->knownIdentifiers();
        $defaultLanguage = $languages[0];

        $typeValue = $request->query->getString('type');
        $currentType = in_array($typeValue, $identifiers, true) ? $typeValue : $identifiers[0];

        $langValue = $request->query->getString('lang');
        $currentLanguage = in_array($langValue, $languages, true) ? $langValue : $defaultLanguage;

        $context = $this->resolveMockContext($currentType, $currentLanguage);

        $adminTop = new AdminTop(info: [new AdminTopInfoText($this->translator->trans('admin_email_debugging.intro'))], actions: [
            $this->buildTypeDropdown($currentType, $currentLanguage),
            $this->buildLanguageDropdown($currentLanguage, $currentType, $languages),
        ]);

        return $this->render('admin/email/debugging/index.html.twig', [
            'active' => 'email',
            'currentType' => $currentType,
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

        $emailType = $this->findType($emailTypeValue);
        if (!$emailType instanceof EmailInterface) {
            $this->addFlash('error', $this->translator->trans('admin_email_debugging.flash_error'));

            return $this->redirectToRoute('app_admin_email_debugging', [
                'type' => $emailTypeValue,
                'lang' => $language,
            ]);
        }

        if ($this->blocklist->isBlocked($recipient)) {
            $this->addFlash('warning', $this->translator->trans('admin_email_debugging.flash_blocked', [
                '%recipient%' => $recipient,
            ]));

            return $this->redirectToRoute('app_admin_email_debugging', [
                'type' => $emailTypeValue,
                'lang' => $language,
            ]);
        }

        try {
            $email = new TemplatedEmail()
                ->from($this->config->getMailerAddress())
                ->to($recipient)
                ->locale($language)
                ->context($context);

            $this->emailQueue->enqueue($emailType, $email, $context);

            $this->addFlash('success', $this->translator->trans('admin_email_debugging.flash_queued', [
                '%recipient%' => $recipient,
            ]));
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
    private function resolveMockContext(string $identifier, string $locale): array
    {
        $emailType = $this->findType($identifier);
        if (!$emailType instanceof EmailInterface) {
            return [];
        }

        $context = $emailType->getDisplayMockData($locale)['context'];
        if (!array_key_exists('greeting', $context)) {
            $context['greeting'] = '';
        }

        return $context;
    }

    private function findType(string $identifier): ?EmailInterface
    {
        foreach ($this->emailTypes as $emailType) {
            if ($emailType->getIdentifier() === $identifier) {
                return $emailType;
            }
        }

        return null;
    }

    private function buildTypeDropdown(string $current, string $language): AdminTopActionDropdown
    {
        $options = [];
        foreach ($this->knownIdentifiers() as $identifier) {
            $options[] = new AdminTopActionDropdownOption(
                label: $this->humanize($identifier),
                target: $this->generateUrl('app_admin_email_debugging', ['type' => $identifier, 'lang' => $language]),
                isActive: $identifier === $current,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf('%s %s', $this->translator->trans('admin_email_debugging.field_email_type') . ':', $this->humanize($current)),
            options: $options,
            icon: 'envelope',
        );
    }

    /**
     * @param list<string> $languages
     */
    private function buildLanguageDropdown(string $current, string $type, array $languages): AdminTopActionDropdown
    {
        $options = [];
        foreach ($languages as $code) {
            $options[] = new AdminTopActionDropdownOption(
                label: $code,
                target: $this->generateUrl('app_admin_email_debugging', ['type' => $type, 'lang' => $code]),
                isActive: $code === $current,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf('%s %s', $this->translator->trans('admin_email_debugging.field_language') . ':', $current),
            options: $options,
            icon: 'language',
        );
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    /** @return list<string> */
    private function knownIdentifiers(): array
    {
        $identifiers = [];
        foreach ($this->emailTypes as $emailType) {
            $identifiers[] = $emailType->getIdentifier();
        }
        sort($identifiers);

        return $identifiers;
    }
}
