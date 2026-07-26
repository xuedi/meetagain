<?php declare(strict_types=1);

namespace App\Command;

use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(FixturesLoaderInterface::class)]
class SymfonyFixturesLoaderAdapter implements FixturesLoaderInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.fixtures.loader')]
        private readonly SymfonyFixturesLoader $loader,
    ) {}

    public function getFixtures(array $groups = []): array
    {
        return $this->loader->getFixtures($groups);
    }
}
