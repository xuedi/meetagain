<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sparse per-locale canonical markers for recurring event series. A row exists only where a
 * series branched; absence means the occurrence follows the latest preceding root. Markers
 * cannot outlive their event (CASCADE), and one event carries at most one marker per locale.
 */
final class Version20260724230732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the event_canonical_root table for per-locale series canonical markers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_canonical_root (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(2) NOT NULL, type VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, event_id INT NOT NULL, INDEX IDX_91E7E93C71F7E88B (event_id), UNIQUE INDEX UNIQ_91E7E93C71F7E88B4180C698 (event_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE event_canonical_root ADD CONSTRAINT FK_91E7E93C71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_canonical_root DROP FOREIGN KEY FK_91E7E93C71F7E88B');
        $this->addSql('DROP TABLE event_canonical_root');
    }
}
