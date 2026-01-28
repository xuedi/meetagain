<?php declare(strict_types=1);

namespace App\AdminModules\Translation;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

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
            new AdminLink(
                label: 'menu_admin_translation_suggestions',
                route: 'app_admin_translation_suggestion',
                active: 'suggestions',
            ),
            new AdminLink(label: 'menu_admin_translation_edit', route: 'app_admin_translation_edit', active: 'edit'),
            new AdminLink(
                label: 'menu_admin_translation_extract',
                route: 'app_admin_translation_extract',
                active: 'extract',
            ),
            new AdminLink(
                label: 'menu_admin_translation_publish',
                route: 'app_admin_translation_publish',
                active: 'publish',
            ),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_translation_suggestion',
                'path' => '/admin/translations/suggestions',
                'controller' => [TranslationController::class, 'translationsSuggestions'],
            ],
            [
                'name' => 'app_admin_translation_edit',
                'path' => '/admin/translations/edit',
                'controller' => [TranslationController::class, 'translationsIndex'],
            ],
            [
                'name' => 'app_admin_translation_save',
                'path' => '/admin/translations/save',
                'controller' => [TranslationController::class, 'translationsSave'],
            ],
            [
                'name' => 'app_admin_translation_extract',
                'path' => '/admin/translations/extract',
                'controller' => [TranslationController::class, 'translationsExtract'],
            ],
            [
                'name' => 'app_admin_translation_publish',
                'path' => '/admin/translations/publish',
                'controller' => [TranslationController::class, 'translationsPublish'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
