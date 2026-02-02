<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;

/**
 * Adapter to use Symfony's fixtures loader with the FixturesLoaderInterface.
 * Allows for testable dependency injection without depending on final classes.
 */
class SymfonyFixturesLoaderAdapter implements FixturesLoaderInterface
{
    public function __construct(
        private readonly SymfonyFixturesLoader $loader,
    ) {}

    public function getFixtures(array $groups = []): array
    {
        return $this->loader->getFixtures($groups);
    }
}
