<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\LanguageService;
use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LanguageService $languageService,
        private string $defaultLocale = 'en',
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', 250],
            ],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->hasPreviousSession()) {
            return;
        }

        // save explicit session, if not available restore from session
        if ($locale = $request->attributes->get('_locale')) {
            $request->getSession()->set('_locale', $locale);
        } else {
            $filteredDefault = $this->languageService->getFilteredDefaultLocale();
            $locale = $request->getSession()->get('_locale', $filteredDefault);
            $request->setLocale($locale);
        }
    }
}
