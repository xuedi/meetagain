<?php declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use App\Repository\ImageRepository;
use App\Service\Config\LanguageService;
use App\Service\Media\AltLocaleRequirementResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class MissingAltImageNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private ImageRepository $imageRepository,
        private Security $security,
        private TranslatorInterface $translator,
        private LanguageService $languageService,
        private AltLocaleRequirementResolver $altLocaleRequirementResolver,
    ) {}

    public function getNotifications(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return [];
        }

        $sourceLocale = $this->languageService->getFilteredDefaultLocale();

        $items = [];
        foreach ($this->imageRepository->findHighUsageMissingAlt() as $candidate) {
            $image = $candidate['image'];
            $missing = $image->missingAltLocales($this->altLocaleRequirementResolver->getRequiredAltLocales($image), $sourceLocale);
            if ($missing === []) {
                continue;
            }

            $items[] = new NotificationItem(
                label: $this->translator->trans('chrome.notification_image_missing_alt', [
                    '%id%' => $image->getId(),
                    '%missing%' => count($missing),
                ]),
                icon: 'fa-image',
                route: 'app_admin_system_images_show',
                routeParams: ['id' => $image->getId()],
            );
        }

        return $items;
    }
}
