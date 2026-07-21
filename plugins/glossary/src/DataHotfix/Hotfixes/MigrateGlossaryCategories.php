<?php declare(strict_types=1);

namespace Plugin\Glossary\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use App\Service\Config\LanguageService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Glossary\Item\GlossaryCategorizableTypeProvider;
use Plugin\Glossary\Migration\LegacyGlossaryCategoryConverter;

/**
 * Migrates glossary onto the shared item taxonomy. Two steps, both idempotent:
 *   1. Copy each entry's legacy `category` int into item_category_assignment (item_type='glossary').
 *      The column is still physically present (its DROP is a later release); the raw read is skipped
 *      where the column no longer exists (dev/test schemas built without it).
 *   2. Rewrite the GLOBAL glossary plugin_settings config from the single-label category shape to the
 *      per-locale taxonomy shape (existing label -> the site's default locale).
 *
 * Per-scope configs stored by a host plugin are rewritten by that plugin's own sibling hotfix; the
 * entry-category backfill above already covers every entry (they are ordinary glossary rows).
 */
readonly class MigrateGlossaryCategories implements DataHotfixInterface
{
    private const string GLOSSARY_TABLE = 'plg_glossary_glossary';

    public function __construct(
        private EntityManagerInterface $em,
        private LanguageService $languageService,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return 'glossary_2026_07_19_migrate_glossary_categories';
    }

    #[Override]
    public function execute(): void
    {
        $connection = $this->em->getConnection();
        $this->backfillAssignments($connection);
        $this->rewriteGlobalConfig($connection);
    }

    private function backfillAssignments(Connection $connection): void
    {
        if (!$this->legacyColumnExists($connection)) {
            return;
        }

        $rows = $connection->fetchAllAssociative(sprintf('SELECT id, category FROM %s WHERE category IS NOT NULL', self::GLOSSARY_TABLE));
        foreach ($rows as $row) {
            $itemId = (int) $row['id'];
            $alreadyAssigned = $connection->fetchOne(
                'SELECT id FROM item_category_assignment WHERE item_type = ? AND item_id = ?',
                [GlossaryCategorizableTypeProvider::ITEM_TYPE, $itemId],
            );
            if ($alreadyAssigned !== false) {
                continue;
            }

            $connection->insert('item_category_assignment', [
                'item_type' => GlossaryCategorizableTypeProvider::ITEM_TYPE,
                'item_id' => $itemId,
                'category_id' => (int) $row['category'],
            ]);
        }
    }

    private function rewriteGlobalConfig(Connection $connection): void
    {
        $row = $connection->fetchAssociative('SELECT id, data FROM plugin_settings WHERE plugin_key = ?', ['glossary']);
        if ($row === false) {
            return;
        }

        $data = json_decode((string) $row['data'], true);
        if (!is_array($data)) {
            return;
        }

        $converted = LegacyGlossaryCategoryConverter::convert($data, $this->languageService->getFilteredDefaultLocale());
        if ($converted === null) {
            return;
        }

        $connection->update('plugin_settings', ['data' => json_encode($converted)], ['id' => $row['id']]);
    }

    private function legacyColumnExists(Connection $connection): bool
    {
        $count = $connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [self::GLOSSARY_TABLE, 'category'],
        );

        return (int) $count > 0;
    }
}
