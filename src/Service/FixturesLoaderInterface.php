<?php declare(strict_types=1);

namespace App\Service;

/**
 * Interface for loading fixtures by group.
 * Allows for testing without depending on final Doctrine classes.
 */
interface FixturesLoaderInterface
{
    /**
     * Get fixtures for the specified groups.
     *
     * @param array<string> $groups Group names to filter by
     * @return array<object> Loaded fixtures
     */
    public function getFixtures(array $groups = []): array;
}
