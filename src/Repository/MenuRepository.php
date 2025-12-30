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
    /** @var array<string, Menu[]>|null Per-request cache of menus by location */
    private ?array $menuCache = null;

    public function __construct(
        ManagerRegistry $registry,
        private readonly RouterInterface $router,
    ) {
        parent::__construct($registry, Menu::class);
    }

    public function getAllSlugified(
        ?UserInterface $user = null,
        string $locale = 'en',
        ?string $location = null,
    ): array {
        // Load all menus once per request
        if ($this->menuCache === null) {
            $this->menuCache = [];
            $allMenus = $this->findBy([], ['priority' => 'ASC']);
            foreach ($allMenus as $menu) {
                $loc = $menu->getLocation()->value ?? 'default';
                $this->menuCache[$loc][] = $menu;
            }
        }

        // Get menus for requested location from cache
        $locationKey = match ($location) {
            'top' => MenuLocation::TopBar->value,
            'col1' => MenuLocation::BottomCol1->value,
            'col2' => MenuLocation::BottomCol2->value,
            'col3' => MenuLocation::BottomCol3->value,
            'col4' => MenuLocation::BottomCol4->value,
            default => null,
        };

        $all = $locationKey !== null
            ? ($this->menuCache[$locationKey] ?? [])
            : array_merge(...array_values($this->menuCache));
        $list = [];
        foreach ($all as $menu) {
            if (!$this->isVisible($user, $menu->getVisibility())) {
                continue;
            }
            $menu->setSlug(match ($menu->getType()) {
                MenuType::Cms => '/' . $locale . '/' . $menu->getCms()?->getSlug(),
                MenuType::Event => '/' . $locale . '/events/' . $menu->getEvent()?->getId(),
                MenuType::Route => $this->router->generate($menu->getRoute()->value),
                MenuType::Url, null => $menu->getSlug(),
            });
            $list[] = $menu;
        }

        return $list;
    }

    private function isVisible(?UserInterface $user, ?MenuVisibility $visibility): bool
    {
        if (!$visibility instanceof MenuVisibility) {
            return true;
        }

        return match ($visibility) {
            MenuVisibility::Everyone => true,
            MenuVisibility::User => $user instanceof UserInterface && in_array('ROLE_USER', $user->getRoles(), true),
            MenuVisibility::Manager => $user instanceof UserInterface && in_array('ROLE_MANAGER', $user->getRoles(), true),
            MenuVisibility::Admin => $user instanceof UserInterface && in_array('ROLE_ADMIN', $user->getRoles(), true),
        };
    }
}
