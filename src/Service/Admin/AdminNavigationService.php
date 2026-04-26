<?php declare(strict_types=1);

namespace App\Service\Admin;

use App\Controller\Admin\AdminNavigationInterface;
use App\Entity\AdminLink;
use App\Entity\AdminSection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

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
        private RouterInterface $router,
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
                $sectionParams = $config->sectionParams ?? [];
                $label = $link->getLabel();
                $route = $link->getRoute();
                $active = $link->getActive();
                $role = $link->getRole();

                if (isset($modifications[$route])) {
                    $mods = $modifications[$route];

                    // Drop the link entirely when explicitly hidden by a modifier.
                    if (($mods['hidden'] ?? false) === true) {
                        continue;
                    }

                    $section = isset($mods['section']) ? (string) $mods['section'] : $section;
                    $label = isset($mods['label']) ? (string) $mods['label'] : $label;
                    $active = isset($mods['active']) ? (string) $mods['active'] : $active;
                    if (isset($mods['sectionParams']) && is_array($mods['sectionParams'])) {
                        $sectionParams = $mods['sectionParams'];
                    }
                    // Swap the route last so the routeExists() check below validates the
                    // replacement; an unknown replacement route fails soft (link dropped).
                    if (isset($mods['route']) && is_string($mods['route']) && $mods['route'] !== '') {
                        $route = $mods['route'];
                    }
                }

                // Initialize section if new
                if (!isset($sectionsMap[$section])) {
                    $sectionsMap[$section] = [
                        'role' => $config->sectionRole,
                        'priority' => $config->sectionPriority,
                        'sectionParams' => $sectionParams,
                        'links' => [],
                    ];
                }

                if (!$this->routeExists($route)) {
                    continue;
                }

                // Create modified link
                $modifiedLink = new AdminLink(label: $label, route: $route, active: $active, role: $role);

                $sectionsMap[$section]['links'][] = $modifiedLink;
            }
        }

        // Sort sections by priority ASC, then alphabetically within same priority
        uksort($sectionsMap, static function (string $a, string $b) use ($sectionsMap): int {
            $diff = $sectionsMap[$a]['priority'] <=> $sectionsMap[$b]['priority'];
            return $diff !== 0 ? $diff : strcmp($a, $b);
        });

        // Sort links within each section alphabetically by label
        foreach ($sectionsMap as &$sectionData) {
            usort($sectionData['links'], static fn($a, $b): int => strcmp($a->getLabel(), $b->getLabel()));
        }

        // Build AdminSection objects and filter by role
        $sections = [];
        foreach ($sectionsMap as $sectionName => $data) {
            // Filter section by role
            if ($data['role'] !== null && !$this->security->isGranted($data['role'])) {
                continue;
            }

            // Filter links by role
            $visibleLinks = array_filter(
                $data['links'],
                fn($link) => $link->getRole() === null || $this->security->isGranted($link->getRole()),
            );

            // Skip sections with no visible links
            if ($visibleLinks === []) {
                continue;
            }

            $sections[] = new AdminSection(
                section: $sectionName,
                links: array_values($visibleLinks),
                role: $data['role'],
                sectionParams: $data['sectionParams'],
            );
        }

        return $sections;
    }

    private function routeExists(string $name): bool
    {
        try {
            $this->router->generate($name);

            return true;
        } catch (RouteNotFoundException) {
            return false;
        } catch (\Exception) {
            return true;
        }
    }
}
