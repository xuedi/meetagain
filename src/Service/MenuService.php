<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class MenuService
{
    public function __construct(
        private MenuRepository $menuRepo,
    ) {
    }

    /**
     * @return Menu[]
     */
    public function getMenuForContext(string $type, ?UserInterface $user, string $locale): array
    {
        return $this->menuRepo->getAllSlugified($user, $locale, $type);
    }
}
