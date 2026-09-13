<?php declare(strict_types=1);

namespace App\Portability;

use App\Item\TypeRegistry;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class KindLabels
{
    private const array CORE = [
        'users' => 'admin_system_import.kind_users',
        'locations' => 'admin_system_import.kind_locations',
        'hosts' => 'admin_system_import.kind_hosts',
        'series' => 'admin_system_import.kind_series',
        'events' => 'admin_system_import.kind_events',
        'rsvps' => 'admin_system_import.kind_rsvps',
        'cms_pages' => 'admin_system_import.kind_cms_pages',
        'announcements' => 'admin_system_import.kind_announcements',
        'tags' => 'admin_system_import.kind_tags',
        'tag_assignments' => 'admin_system_import.kind_tag_assignments',
        'event_items' => 'admin_system_import.kind_event_items',
        'topics' => 'admin_system_import.kind_topics',
        'circulation_copies' => 'admin_system_import.kind_circulation_copies',
        'circulation_requests' => 'admin_system_import.kind_circulation_requests',
        'circulation_handovers' => 'admin_system_import.kind_circulation_handovers',
        'circulation_ledger' => 'admin_system_import.kind_circulation_ledger',
        'comments' => 'admin_system_import.kind_comments',
        'change_proposals' => 'admin_system_import.kind_change_proposals',
        'ballots' => 'admin_system_import.kind_ballots',
        'trust' => 'admin_system_import.kind_trust',
    ];

    /**
     * @param iterable<SectionInterface> $sections
     */
    public function __construct(
        #[AutowireIterator(SectionInterface::class)]
        private iterable $sections,
        private TypeRegistry $itemTypeRegistry,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param list<string> $kinds
     * @return array<string, string>
     */
    public function labelsFor(array $kinds): array
    {
        $pluginLabels = [];
        foreach ($this->sections as $section) {
            if (!$section instanceof PluginSectionInterface) {
                continue;
            }

            $pluginLabels = [...$pluginLabels, ...$section->getKindLabels()];
        }

        $labels = [];
        foreach ($kinds as $kind) {
            $labelKey = self::CORE[$kind] ?? $pluginLabels[$kind] ?? $this->itemTypeRegistry->providerForIncludingInactive($kind)?->getLabelKey();
            $labels[$kind] = $labelKey === null ? $kind : $this->translator->trans($labelKey);
        }

        return $labels;
    }
}
