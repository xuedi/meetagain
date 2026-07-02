<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace event.recurring_of/recurring_rule with an explicit event_series entity; down() is best-effort and data-lossy (series names are dropped)';
    }

    public function up(Schema $schema): void
    {
        // Steps 1-4 run immediately (not via addSql) because the backfill guards in step 4 must
        // see their results before the destructive drops in step 5 are queued. MariaDB DDL is
        // non-transactional: an aborted run leaves the old columns untouched plus a harmless
        // event_series table - fix and retry after dropping it.

        // 1. New series table; origin_event_id is a temporary backfill helper dropped at the end.
        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE event_series (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                rule INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                origin_event_id INT DEFAULT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->connection->executeStatement('ALTER TABLE event ADD series_id INT DEFAULT NULL');
        $this->connection->executeStatement('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA75278319C FOREIGN KEY (series_id) REFERENCES event_series (id) ON DELETE SET NULL');
        $this->connection->executeStatement('CREATE INDEX IDX_3BAE0AA75278319C ON event (series_id)');

        // 2. One series per rule-carrying event, named after its en title (fallback: any
        //    translation with the lowest language code, then "Series #<old parent id>").
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO event_series (name, rule, created_at, origin_event_id)
            SELECT COALESCE(
                       NULLIF(et.title, ''),
                       (SELECT et2.title FROM event_translation et2 WHERE et2.event_id = e.id ORDER BY et2.language LIMIT 1),
                       CONCAT('Series #', e.id)
                   ),
                   e.recurring_rule,
                   e.created_at,
                   e.id
            FROM event e
            LEFT JOIN event_translation et ON et.event_id = e.id AND et.language = 'en'
            WHERE e.recurring_rule IS NOT NULL
        SQL);

        // 3. Link former parents and their children to the new series rows. Children whose
        //    recurring_of points at a deleted row intentionally stay series-less and become
        //    standalone events.
        $this->connection->executeStatement('UPDATE event e JOIN event_series s ON s.origin_event_id = e.id SET e.series_id = s.id');
        $this->connection->executeStatement('UPDATE event e JOIN event_series s ON s.origin_event_id = e.recurring_of SET e.series_id = s.id');

        // 4. Verify the backfill BEFORE dropping columns so a failed run leaves the old data intact.
        $childMissed = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM event e JOIN event e2 ON e2.id = e.recurring_of WHERE e.series_id IS NULL',
        );
        $this->abortIf($childMissed > 0, sprintf('%d child events with a living parent missed the series backfill', $childMissed));
        $parentMissed = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM event WHERE recurring_rule IS NOT NULL AND series_id IS NULL',
        );
        $this->abortIf($parentMissed > 0, sprintf('%d rule-carrying events missed the series backfill', $parentMissed));

        // 5. Destructive steps last.
        $this->addSql('ALTER TABLE event_series DROP COLUMN origin_event_id');
        $this->addSql('ALTER TABLE event DROP COLUMN recurring_of, DROP COLUMN recurring_rule');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD recurring_of INT DEFAULT NULL, ADD recurring_rule INT DEFAULT NULL');

        // The earliest member (start ASC, id ASC) of each series becomes the parent again; the
        // series name is lost.
        $this->addSql(<<<'SQL'
            UPDATE event e
            JOIN (
                SELECT firsts.series_id, MIN(firsts.id) AS anchor_id
                FROM (
                    SELECT e1.id, e1.series_id
                    FROM event e1
                    JOIN (
                        SELECT series_id, MIN(start) AS min_start
                        FROM event
                        WHERE series_id IS NOT NULL
                        GROUP BY series_id
                    ) fm ON fm.series_id = e1.series_id AND fm.min_start = e1.start
                ) firsts
                GROUP BY firsts.series_id
            ) a ON a.series_id = e.series_id
            JOIN event_series s ON s.id = e.series_id
            SET e.recurring_rule = IF(e.id = a.anchor_id, s.rule, NULL),
                e.recurring_of = IF(e.id = a.anchor_id, NULL, a.anchor_id)
        SQL);

        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA75278319C');
        $this->addSql('DROP INDEX IDX_3BAE0AA75278319C ON event');
        $this->addSql('ALTER TABLE event DROP COLUMN series_id');
        $this->addSql('DROP TABLE event_series');
    }
}
