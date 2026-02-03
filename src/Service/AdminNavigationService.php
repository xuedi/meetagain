<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\AdminLink;
use App\Entity\AdminSection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds admin sidebar navigation from static YAML configuration and plugin extensions.
 *
 * Core sections are defined in config/admin_navigation.yaml.
 * Plugins can add dynamic sections by implementing AdminNavigationExtensionInterface.
 */
readonly class AdminNavigationService
{
    private array $config;

    /**
     * @param iterable<AdminNavigationExtensionInterface> $extensions
     */
    public function __construct(
        private Security $security,
        #[AutowireIterator(AdminNavigationExtensionInterface::class)]
        private iterable $extensions,
        string $kernelProjectDir,
    ) {
        $configPath = $kernelProjectDir . '/config/admin_navigation.yaml';
        $this->config = Yaml::parseFile($configPath)['admin_navigation'] ?? [];
    }

    /**
     * Get all sidebar sections for the current user.
     *
     * Merges static YAML sections with dynamic plugin-provided sections,
     * sorted by priority (higher values appear first).
     *
     * @return list<AdminSection>
     */
    public function getSidebarSections(): array
    {
        $sectionsWithPriority = [];

        // Build static sections from YAML
        $staticSections = $this->config['sections'] ?? [];
        foreach ($staticSections as $sectionKey => $sectionConfig) {
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
                $sectionsWithPriority[] = [
                    'priority' => $sectionConfig['priority'] ?? 0,
                    'section' => new AdminSection(section: $sectionConfig['label'], links: $links),
                ];
            }
        }

        // Collect sections from plugin extensions
        foreach ($this->getSortedExtensions() as $extension) {
            foreach ($extension->getAdminSections() as $section) {
                $sectionsWithPriority[] = [
                    'priority' => $extension->getPriority(),
                    'section' => $section,
                ];
            }
        }

        // Sort all sections by priority (higher first)
        usort($sectionsWithPriority, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);

        return array_column($sectionsWithPriority, 'section');
    }

    /**
     * Get extensions sorted by priority (higher first).
     *
     * @return array<AdminNavigationExtensionInterface>
     */
    private function getSortedExtensions(): array
    {
        $extensions = iterator_to_array($this->extensions);
        usort($extensions, static fn($a, $b): int => $b->getPriority() <=> $a->getPriority());
        return $extensions;
    }
}
