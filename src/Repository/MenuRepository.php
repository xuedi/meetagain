<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Menu;
use App\Entity\MenuLocation;
use App\Entity\MenuType;
use App\Entity\MenuVisibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<Menu>
 */
class MenuRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly RouterInterface $router,
    ) {
        parent::__construct($registry, Menu::class);
    }

    public function getAllSlugified(
        null|UserInterface $user = null,
        string $locale = 'en',
        null|string $location = null,
    ): array {
        $criteria = match ($location) {
            'top' => ['location' => MenuLocation::TopBar],
            'col1' => ['location' => MenuLocation::BottomCol1],
            'col2' => ['location' => MenuLocation::BottomCol2],
            'col3' => ['location' => MenuLocation::BottomCol3],
            'col4' => ['location' => MenuLocation::BottomCol4],
            default => [],
        };
        $all = $this->findBy($criteria, ['priority' => 'ASC']);
        $list = [];
        foreach ($all as $menu) {
            if (!$this->isVisible($user, $menu->getVisibility())) {
                continue;
            }
            $menu->setSlug(match ($menu->getType()) {
                MenuType::Cms => '/' . $locale . '/' . $menu->getCms()?->getSlug(),
                MenuType::Event => '/' . $locale . '/events/' . $menu->getEvent()?->getId(),
                MenuType::Route => $this->router->generate($menu->getRoute()->value),
                MenuType::Url => $menu->getSlug(),
            });
            $list[] = $menu;
        }

        return $list;
    }

    private function isVisible(null|UserInterface $user, null|MenuVisibility $visibility): bool
    {
        if ($visibility === null) {
            return true;
        }

        return match ($visibility) {
            MenuVisibility::Everyone => true,
            MenuVisibility::User => $user !== null && $user->hasRole('ROLE_USER'),
            MenuVisibility::Manager => $user !== null && $user->hasRole('ROLE_MANAGER'),
            MenuVisibility::Admin => $user !== null && $user->hasRole('ROLE_ADMIN'),
            default => false,
        };
    }
}
