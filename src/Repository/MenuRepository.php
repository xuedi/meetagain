<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Menu;
use App\Entity\MenuType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Routing\RouterInterface;

/**
 * @extends ServiceEntityRepository<Menu>
 */
class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly RouterInterface $router)
    {
        parent::__construct($registry, Menu::class);
    }

    public function getAllSlugified(string $locale = 'en'): array
    {
        $all = $this->findAll();
        $list = [];
        foreach ($all as $menu) {
            $menu->setSlug(match ($menu->getType()) {
                MenuType::Cms => '/' . $locale . '/' . $menu->getCms()->getSlug(),
                MenuType::Event => '/' . $locale . '/events/' . $menu->getEvent()->getId(),
                MenuType::Route => $this->router->generate($menu->getRoute()->value),
                MenuType::Slug => $menu->getSlug(),
            });
            $list[] = $menu;
        }

        return $list;
    }
}
