<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\AdminSection;
use App\Service\AdminService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminService $adminService,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_admin_sections', $this->getAdminSections(...)),
        ];
    }

    /**
     * @return list<AdminSection>
     */
    public function getAdminSections(): array
    {
        return $this->adminService->getSidebarSections();
    }
}
