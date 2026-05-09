<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop logs_incident.brute_force_hits column and stale brute-force aggregator watermark; rate-limit source now ingests every limiter.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE logs_incident DROP COLUMN brute_force_hits');
        $this->addSql(<<<'SQL'
            DELETE FROM app_state WHERE key_name = 'security.incident.brute_force.last_processed_log_id'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE logs_incident ADD brute_force_hits INT DEFAULT 0 NOT NULL');
    }
}
