<?php declare(strict_types=1);

namespace App\Service;

use App\Controller\Admin\AdminNavigationInterface;
use App\Entity\AdminLink;
use App\Entity\AdminSection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Builds admin sidebar navigation from controllers.
 *
 * All admin controllers implement AdminNavigationInterface to define their navigation.
 * Sections and links are sorted alphabetically.
 */
readonly class AdminNavigationService
{
    /**
     * @param iterable<AdminNavigationInterface> $controllers
     */
    public function __construct(
        private Security $security,
        #[AutowireIterator(AdminNavigationInterface::class)]
        private iterable $controllers,
    ) {}

    /**
     * Get all sidebar sections for the current user.
     *
     * Collects navigation from all controllers, groups by section, sorts alphabetically,
     * and filters by user roles.
     *
     * @return list<AdminSection>
     */
    public function getSidebarSections(): array
    {
        $sectionsMap = [];

        // Collect from controllers
        foreach ($this->controllers as $controller) {
            $config = $controller->getAdminNavigation();
            if ($config === null) {
                continue; // Skip controllers without navigation
            }

            $sectionKey = $config->section;

            // Initialize section if new
            if (!isset($sectionsMap[$sectionKey])) {
                $sectionsMap[$sectionKey] = [
                    'role' => $config->sectionRole,
                    'links' => [],
                ];
            }

            // Add link to section
            $sectionsMap[$sectionKey]['links'][] = new AdminLink(
                label: $config->label,
                route: $config->route,
                active: $config->active,
                role: $config->linkRole,
            );
        }

        // Sort sections alphabetically by section name
        ksort($sectionsMap);

        // Sort links within each section alphabetically by label
        foreach ($sectionsMap as &$sectionData) {
            usort($sectionData['links'], static fn($a, $b): int => strcmp($a->getLabel(), $b->getLabel()));
        }

        // Build AdminSection objects and filter by role
        $sections = [];
        foreach ($sectionsMap as $sectionName => $data) {
            // Filter section by role
            if ($data['role'] && !$this->security->isGranted($data['role'])) {
                continue;
            }

            // Filter links by role
            $visibleLinks = array_filter(
                $data['links'],
                fn($link) => !$link->getRole() || $this->security->isGranted($link->getRole()),
            );

            // Skip sections with no visible links
            if (empty($visibleLinks)) {
                continue;
            }

            $sections[] = new AdminSection(
                section: $sectionName,
                links: array_values($visibleLinks),
                role: $data['role'],
            );
        }

        return $sections;
    }
}
