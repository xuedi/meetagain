<?php declare(strict_types=1);

namespace Plugin\Glossary\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use App\Service\Config\LanguageService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Migration\LegacyGlossaryCategoryConverter;

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
                [GlossaryTaggableTypeProvider::ITEM_TYPE, $itemId],
            );
            if ($alreadyAssigned !== false) {
                continue;
            }

            $connection->insert('item_category_assignment', [
                'item_type' => GlossaryTaggableTypeProvider::ITEM_TYPE,
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
