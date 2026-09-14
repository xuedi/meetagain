<?php declare(strict_types=1);

namespace App\Portability;

/**
 * A section a plugin owns. It exports only when the scope lists its plugin and imports only while
 * the plugin is active; otherwise the archive's rows under its key count as skipped.
 */
interface PluginSectionInterface extends SectionInterface
{
    public function getPluginKey(): string;

    /**
     * @return array<string, string> kind => translation key
     */
    public function getKindLabels(): array;
}
