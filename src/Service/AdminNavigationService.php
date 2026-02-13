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
     * @return list<AdminSection>
     */
    public function getSidebarSections(): array
    {
        $sectionsMap = [];
        $modifications = [];

        // First pass: collect all route modifications
        foreach ($this->controllers as $controller) {
            $config = $controller->getAdminNavigation();
            if ($config === null) {
                continue;
            }

            if ($config->modifies !== null) {
                foreach ($config->modifies as $route => $changes) {
                    $modifications[$route] = $changes;
                }
            }
        }

        // Second pass: collect navigation, applying modifications
        foreach ($this->controllers as $controller) {
            $config = $controller->getAdminNavigation();
            if ($config === null) {
                continue; // Skip controllers without navigation
            }

            foreach ($config->links as $link) {
                // Apply modifications if they exist for this route
                $section = $config->section;
                $label = $link->getLabel();
                $route = $link->getRoute();
                $active = $link->getActive();
                $role = $link->getRole();

                if (isset($modifications[$route])) {
                    $mods = $modifications[$route];
                    $section = $mods['section'] ?? $section;
                    $label = $mods['label'] ?? $label;
                    $active = $mods['active'] ?? $active;
                    $role = $mods['role'] ?? $role;
                }

                // Initialize section if new
                if (!isset($sectionsMap[$section])) {
                    $sectionsMap[$section] = [
                        'role' => $config->sectionRole,
                        'links' => [],
                    ];
                }

                // Create modified link
                $modifiedLink = new AdminLink(label: $label, route: $route, active: $active, role: $role);

                $sectionsMap[$section]['links'][] = $modifiedLink;
            }
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
