<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\LanguageService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class LocaleValidationSubscriber implements EventSubscriberInterface
{
    public function __construct(private LanguageService $languageService)
    {
    }

    #[\Override]
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
        $request = $event->getRequest();

        // Check for locale in route attributes
        $locale = $request->attributes->get('_locale') ?? $request->attributes->get('locale');

        if ($locale === null) {
            return;
        }

        // Validate that the locale is enabled
        if (!$this->languageService->isValidCode($locale)) {
            throw new NotFoundHttpException(sprintf('Locale "%s" is not available', $locale));
        }
    }
}
