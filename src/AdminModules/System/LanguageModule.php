<?php declare(strict_types=1);

namespace App\AdminModules\System;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use App\Entity\User;
use App\Entity\UserRole;
use App\Security\Attribute\RequiresRole;
use Symfony\Bundle\SecurityBundle\Security;

#[RequiresRole(UserRole::Admin)]
readonly class LanguageModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'language';
    }

    public function getPriority(): int
    {
        return 970; // After System, Plugin, and Email
    }

    public function getSectionName(): string
    {
        return 'System';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_language', route: 'app_admin_language', active: 'language'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_language',
                'path' => '/admin/language',
                'controller' => [LanguageController::class, 'list'],
            ],
            [
                'name' => 'app_admin_language_add',
                'path' => '/admin/language/add',
                'controller' => [LanguageController::class, 'add'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_language_edit',
                'path' => '/admin/language/{id}/edit',
                'controller' => [LanguageController::class, 'edit'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_language_toggle',
                'path' => '/admin/language/{id}/toggle',
                'controller' => [LanguageController::class, 'toggle'],
                'methods' => ['POST'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof \App\Entity\User) {
            return false;
        }
        return $user->hasUserRole(\App\Entity\UserRole::Admin);
    }
}
