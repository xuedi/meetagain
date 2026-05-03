<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use App\Repository\ImageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class MissingAltImageNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private ImageRepository $imageRepository,
        private Security $security,
        private TranslatorInterface $translator,
    ) {}

    public function getNotifications(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return [];
        }

        $items = [];
        foreach ($this->imageRepository->findHighUsageMissingAlt() as $row) {
            $items[] = new NotificationItem(
                label: $this->translator->trans('chrome.notification_image_missing_alt', [
                    '%id%' => $row['id'],
                    '%count%' => $row['count'],
                ]),
                icon: 'fa-image',
                route: 'app_admin_system_images_show',
                routeParams: ['id' => $row['id']],
            );
        }

        return $items;
    }
}
