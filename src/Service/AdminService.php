<?php declare(strict_types=1);

namespace App\Service;

use App\AdminModules\AdminModuleInterface;
use App\Entity\AdminSection;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Central service for managing admin modules.
 * Collects all modules and builds sidebar structure.
 */
readonly class AdminService
{
    /**
     * @param iterable<AdminModuleInterface> $modules
     */
    public function __construct(
        #[AutowireIterator(AdminModuleInterface::class)]
        private iterable $modules,
    ) {}

    /**
     * Get all sidebar sections for the current user.
     * Returns the exact same structure as the current system.
     *
     * @return list<AdminSection>
     */
    public function getSidebarSections(): array
    {
        $sectionMap = [];

        // Group modules by section name
        foreach ($this->getSortedModules() as $module) {
            if (!$module->isAccessible()) {
                continue;
            }

            $sectionName = $module->getSectionName();

            if (!isset($sectionMap[$sectionName])) {
                $sectionMap[$sectionName] = [];
            }

            // Merge all links from this module
            array_push($sectionMap[$sectionName], ...$module->getLinks());
        }

        // Build AdminSection objects (same as current plugin system)
        $sections = [];
        foreach ($sectionMap as $sectionName => $links) {
            $sections[] = new AdminSection($sectionName, $links);
        }

        return $sections;
    }

    /**
     * Get all modules (used by route loader).
     *
     * @return iterable<AdminModuleInterface>
     */
    public function getAllModules(): iterable
    {
        return $this->modules;
    }

    /**
     * Get all modules sorted by priority.
     *
     * @return array<AdminModuleInterface>
     */
    private function getSortedModules(): array
    {
        $modules = iterator_to_array($this->modules);

        usort(
            $modules,
            static fn(AdminModuleInterface $a, AdminModuleInterface $b): int => $b->getPriority() <=> $a->getPriority(),
        );

        return $modules;
    }
}
