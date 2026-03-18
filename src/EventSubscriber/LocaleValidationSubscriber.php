<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Config\LanguageService;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class LocaleValidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LanguageService $languageService,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', 200], // After LocaleSubscriber (250)
            ],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Check for locale in route attributes
        $locale = $request->attributes->get('_locale') ?? $request->attributes->get('locale');

        if ($locale === null) {
            return;
        }

        // Globally disabled language -> 404
        if (!$this->languageService->isValidCode($locale)) {
            throw new NotFoundHttpException(sprintf('Locale "%s" is not available', $locale));
        }

        // Context-filtered language -> redirect to default filtered locale
        if (!$this->languageService->isFilteredValidCode($locale)) {
            $defaultLocale = $this->languageService->getFilteredDefaultLocale();
            $uri = $request->getRequestUri();
            $newUri = preg_replace('#^/' . preg_quote($locale, '#') . '(/|$)#', '/' . $defaultLocale . '$1', $uri);
            $event->setResponse(new RedirectResponse($newUri, 302));
        }
    }
}
