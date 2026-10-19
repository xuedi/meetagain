<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019130000 extends AbstractMigration
{
    private const int REMOVED_TYPE = 16;

    public function getDescription(): string
    {
        return 'Delete the images of the removed developer app logo type; down() cannot bring them back';
    }

    public function up(Schema $schema): void
    {
        $imageIds = array_map(intval(...), $this->connection->fetchFirstColumn('SELECT id FROM image WHERE type = ?', [self::REMOVED_TYPE]));
        $inUse = $this->inUse($imageIds);
        $this->abortIf(
            $inUse !== [],
            sprintf(
                'Images %s still carry the removed type %d but are used elsewhere; retype them before migrating.',
                implode(', ', $inUse),
                self::REMOVED_TYPE,
            ),
        );

        $this->addSql('DELETE FROM image_location WHERE location_type = ?', [self::REMOVED_TYPE]);
        $this->addSql('DELETE FROM image WHERE type = ?', [self::REMOVED_TYPE]);
    }

    public function down(Schema $schema): void {}

    /**
     * @param list<int> $imageIds
     * @return list<int>
     */
    private function inUse(array $imageIds): array
    {
        if ($imageIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
        $used = $this->connection->fetchFirstColumn(
            sprintf('SELECT image_id FROM image_location WHERE location_type <> ? AND image_id IN (%s)', $placeholders),
            [self::REMOVED_TYPE, ...$imageIds],
        );

        $references = $this->connection->fetchAllAssociative(<<<'SQL'
                SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'image'
                  AND TABLE_NAME NOT IN ('image_location', 'image_report')
            SQL);
        foreach ($references as $reference) {
            $used = [
                ...$used,
                ...$this->connection->fetchFirstColumn(
                    sprintf('SELECT `%1$s` FROM `%2$s` WHERE `%1$s` IN (%3$s)', $reference['COLUMN_NAME'], $reference['TABLE_NAME'], $placeholders),
                    $imageIds,
                ),
            ];
        }

        return array_values(array_unique(array_map(intval(...), $used)));
    }
}
