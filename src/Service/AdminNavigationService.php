<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\AdminLink;
use App\Entity\AdminSection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds admin sidebar navigation from static YAML configuration.
 * Replaces the dynamic AdminService/AdminModuleInterface system.
 */
readonly class AdminNavigationService
{
    private array $config;

    public function __construct(
        private Security $security,
        string $kernelProjectDir,
    ) {
        $configPath = $kernelProjectDir . '/config/admin_navigation.yaml';
        $this->config = Yaml::parseFile($configPath)['admin_navigation'] ?? [];
    }

    /**
     * Get all sidebar sections for the current user.
     * Returns the exact same structure as the previous AdminService implementation.
     *
     * @return list<AdminSection>
     */
    public function getSidebarSections(): array
    {
        $sections = [];

        // Sort sections by priority (higher = first)
        $sortedSections = $this->config['sections'] ?? [];
        uasort($sortedSections, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);

        foreach ($sortedSections as $sectionKey => $sectionConfig) {
            // Check if user has required role for this section
            $requiredRole = $sectionConfig['role'] ?? null;
            if ($requiredRole && !$this->security->isGranted($requiredRole)) {
                continue;
            }

            // Build AdminLink objects
            $links = [];
            foreach ($sectionConfig['links'] ?? [] as $linkConfig) {
                $links[] = new AdminLink(
                    label: $linkConfig['label'],
                    route: $linkConfig['route'],
                    active: $linkConfig['active'] ?? null,
                );
            }

            // Only add section if it has links
            if (!empty($links)) {
                $sections[] = new AdminSection(section: $sectionConfig['label'], links: $links);
            }
        }

        return $sections;
    }
}
