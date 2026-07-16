<?php declare(strict_types=1);

namespace Plugin\Dishes\DataHotfix;

use App\DataHotfix\DataHotfixInterface;
use Doctrine\DBAL\Connection;
use Override;

/**
 * One-shot production migration from the removed dinnerclub schema to the dishes schema.
 *
 * Runs entirely on raw SQL so it does not depend on the (deleted) dinnerclub entity mappings.
 * The dishes tables are created by PluginDishesMigrations; the old dinnerclub tables still exist
 * on production until a later follow-up migration drops them. Steps, in order:
 *   1. copy `dish` -> `dishes_dish` (dropping approved/suggestions), preserving ids
 *   2. copy `dish_translation` -> `dishes_dish_translation`, preserving ids
 *   3. copy `dinnerclub_dish_image` -> `dishes_dish_image`, preserving ids (same core Image rows)
 *   4. flatten each `dinner`/`dinner_course`/`dinner_course_item` into `event_item_association`
 *      rows (item_type='dish', position from course+item order, section_label from course name)
 *
 * Ids are preserved so the association item_id equals the original dish id and every step is
 * idempotent by primary-key existence. No-ops on fresh installs where the source tables are absent.
 */
readonly class MigrateDinnerclubToDishes implements DataHotfixInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return 'dishes_2026_07_12_migrate_dinnerclub_to_dishes';
    }

    #[Override]
    public function execute(): void
    {
        if (!$this->tableExists('dish') || !$this->tableExists('dinner')) {
            return;
        }

        $this->copyDishes();
        $this->copyTranslations();
        $this->copyGalleryImages();
        $this->flattenDinnersToAssociations();
    }

    private function copyDishes(): void
    {
        foreach ($this->connection->iterateAssociative('SELECT * FROM dish') as $row) {
            $id = (int) $row['id'];
            if ($this->rowExists('dishes_dish', 'id', $id)) {
                continue;
            }

            $this->connection->insert('dishes_dish', [
                'id' => $id,
                'preview_image_id' => $row['preview_image_id'],
                'pronunciation_system_id' => $row['pronunciation_system_id'],
                'phonetic' => $row['phonetic'],
                'likes' => (int) ($row['likes'] ?? 0),
                'origin' => $row['origin'],
                'created_by' => (int) $row['created_by'],
                'created_at' => $row['created_at'],
            ]);
        }
    }

    private function copyTranslations(): void
    {
        foreach ($this->connection->iterateAssociative('SELECT * FROM dish_translation') as $row) {
            $id = (int) $row['id'];
            if ($this->rowExists('dishes_dish_translation', 'id', $id)) {
                continue;
            }
            if (!$this->rowExists('dishes_dish', 'id', (int) $row['dish_id'])) {
                continue;
            }

            $this->connection->insert('dishes_dish_translation', [
                'id' => $id,
                'dish_id' => (int) $row['dish_id'],
                'language' => $row['language'],
                'name' => $row['name'],
                'description' => $row['description'] ?? '',
                'recipe' => $row['recipe'],
            ]);
        }
    }

    private function copyGalleryImages(): void
    {
        foreach ($this->connection->iterateAssociative('SELECT * FROM dinnerclub_dish_image') as $row) {
            $id = (int) $row['id'];
            if ($this->rowExists('dishes_dish_image', 'id', $id)) {
                continue;
            }
            if (!$this->rowExists('dishes_dish', 'id', (int) $row['dish_id'])) {
                continue;
            }

            $this->connection->insert('dishes_dish_image', [
                'id' => $id,
                'dish_id' => (int) $row['dish_id'],
                'image_id' => (int) $row['image_id'],
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'created_at' => $row['created_at'],
            ]);
        }
    }

    private function flattenDinnersToAssociations(): void
    {
        foreach ($this->connection->iterateAssociative('SELECT id, event_id, created_by, created_at FROM dinner') as $dinner) {
            $eventId = (int) $dinner['event_id'];
            $createdBy = (int) $dinner['created_by'];
            $createdAt = $dinner['created_at'];
            $position = 0;

            $courses = $this->connection->fetchAllAssociative(
                'SELECT id, name FROM dinner_course WHERE dinner_id = ? ORDER BY sort_order ASC, id ASC',
                [(int) $dinner['id']],
            );

            foreach ($courses as $course) {
                $items = $this->connection->fetchAllAssociative(
                    'SELECT dish_id FROM dinner_course_item WHERE course_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC',
                    [(int) $course['id']],
                );

                foreach ($items as $item) {
                    $dishId = (int) $item['dish_id'];
                    // A dish can appear in several courses; the association unique (event,type,item)
                    // keeps only the first. The running position preserves the menu order regardless.
                    if ($this->rowExists('dishes_dish', 'id', $dishId) && !$this->associationExists($eventId, $dishId)) {
                        $this->connection->insert('event_item_association', [
                            'event_id' => $eventId,
                            'item_type' => 'dish',
                            'item_id' => $dishId,
                            'created_by' => $createdBy,
                            'created_at' => $createdAt,
                            'position' => $position,
                            'section_label' => $course['name'],
                        ]);
                    }

                    $position++;
                }
            }
        }
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table],
        ) > 0;
    }

    private function rowExists(string $table, string $column, int $value): bool
    {
        return $this->connection->fetchOne(sprintf('SELECT 1 FROM %s WHERE %s = ?', $table, $column), [$value]) !== false;
    }

    private function associationExists(int $eventId, int $dishId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM event_item_association WHERE event_id = ? AND item_type = ? AND item_id = ?',
            [$eventId, 'dish', $dishId],
        ) !== false;
    }
}
