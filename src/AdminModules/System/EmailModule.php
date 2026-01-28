<?php declare(strict_types=1);

namespace App\AdminModules\System;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

readonly class EmailModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'email';
    }

    public function getPriority(): int
    {
        return 980; // After System and Plugin
    }

    public function getSectionName(): string
    {
        return 'System';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_email', route: 'app_admin_email', active: 'email'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_email',
                'path' => '/admin/email/',
                'controller' => [EmailController::class, 'list'],
            ],
            [
                'name' => 'app_admin_email_edit',
                'path' => '/admin/email/{id}/edit',
                'controller' => [EmailController::class, 'edit'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_email_preview',
                'path' => '/admin/email/{id}/preview',
                'controller' => [EmailController::class, 'preview'],
            ],
            [
                'name' => 'app_admin_email_reset',
                'path' => '/admin/email/{id}/reset',
                'controller' => [EmailController::class, 'reset'],
                'methods' => ['POST'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
