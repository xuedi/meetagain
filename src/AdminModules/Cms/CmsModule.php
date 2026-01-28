<?php declare(strict_types=1);

namespace App\AdminModules\Cms;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminLink;
use Symfony\Bundle\SecurityBundle\Security;

readonly class CmsModule implements AdminModuleInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function getKey(): string
    {
        return 'cms';
    }

    public function getPriority(): int
    {
        return 600; // Start of CMS section
    }

    public function getSectionName(): string
    {
        return 'CMS';
    }

    public function getLinks(): array
    {
        return [
            new AdminLink(label: 'menu_admin_cms', route: 'app_admin_cms', active: 'cms'),
        ];
    }

    public function getRoutes(): array
    {
        return [
            [
                'name' => 'app_admin_cms',
                'path' => '/admin/cms/',
                'controller' => [CmsController::class, 'cmsList'],
            ],
            [
                'name' => 'app_admin_cms_edit',
                'path' => '/admin/cms/{id}/edit/{locale}/{blockId}',
                'controller' => [CmsController::class, 'cmsEdit'],
                'methods' => ['GET', 'POST'],
            ],
            [
                'name' => 'app_admin_cms_delete',
                'path' => '/admin/cms/delete',
                'controller' => [CmsController::class, 'cmsDelete'],
                'methods' => ['GET'],
            ],
            [
                'name' => 'app_admin_cms_add',
                'path' => '/admin/cms/add',
                'controller' => [CmsController::class, 'cmsAdd'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'app_admin_cms_add_block',
                'path' => '/admin/cms/block/{id}/add',
                'controller' => [CmsController::class, 'cmsBlockAdd'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'app_admin_cms_edit_block_down',
                'path' => '/admin/cms/block/down',
                'controller' => [CmsController::class, 'cmsBlockMoveDown'],
                'methods' => ['GET'],
            ],
            [
                'name' => 'app_admin_cms_edit_block_up',
                'path' => '/admin/cms/block/up',
                'controller' => [CmsController::class, 'cmsBlockMoveUp'],
                'methods' => ['GET'],
            ],
            [
                'name' => 'app_admin_cms_edit_block_save',
                'path' => '/admin/cms/block/save',
                'controller' => [CmsController::class, 'cmsBlockSave'],
                'methods' => ['POST'],
            ],
            [
                'name' => 'app_admin_cms_block_delete',
                'path' => '/admin/cms/block/delete',
                'controller' => [CmsController::class, 'cmsBlockDelete'],
                'methods' => ['GET'],
            ],
        ];
    }

    public function isAccessible(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
