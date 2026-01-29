<?php declare(strict_types=1);

namespace App\AdminModules\Translation;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use App\Entity\User;
use App\Entity\UserRole;
use App\Security\Attribute\RequiresRole;
use Symfony\Bundle\SecurityBundle\Security;

#[RequiresRole(UserRole::Admin)]
readonly class TranslationModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'translation';
    }

    public function getPriority(): int
    {
        return 500; // Start of Translation section
    }

    public function getSectionName(): string
    {
        return 'Translation';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_translation', route: 'app_admin_translation', active: 'translation'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_translation',
                'path' => '/admin/translation',
                'controller' => [TranslationController::class, 'translationList'],
            ],
            [
                'name' => 'app_admin_translation_add',
                'path' => '/admin/translation/add',
                'controller' => [TranslationController::class, 'translationAdd'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'app_admin_translation_edit',
                'path' => '/admin/translation/edit/{id}',
                'controller' => [TranslationController::class, 'translationEdit'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'app_admin_translation_delete',
                'path' => '/admin/translation/delete',
                'controller' => [TranslationController::class, 'translationDelete'],
                'methods' => ['GET'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }
        return $user->hasUserRole(UserRole::Admin);
    }
}
