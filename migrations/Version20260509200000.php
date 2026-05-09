<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reshape logs_incident around live block events: drop per-source counters, add session_id / triggered_by / provider_reports / blocked_until_description.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM logs_incident');
        $this->addSql(<<<'SQL'
            DELETE FROM app_state
            WHERE key_name LIKE 'security.incident.%'
               OR key_name = 'cron.incident_aggregator.last_run'
        SQL);

        $this->addSql('ALTER TABLE logs_incident DROP COLUMN probing_hits');
        $this->addSql('ALTER TABLE logs_incident DROP COLUMN access_denied_hits');
        $this->addSql('ALTER TABLE logs_incident DROP COLUMN rate_limit_hits');
        $this->addSql('ALTER TABLE logs_incident DROP COLUMN total_hits');
        $this->addSql('ALTER TABLE logs_incident DROP COLUMN distinct_paths');
        $this->addSql('ALTER TABLE logs_incident DROP COLUMN distinct_user_agents');
        $this->addSql('ALTER TABLE logs_incident DROP COLUMN sample_urls');

        $this->addSql("ALTER TABLE logs_incident ADD session_id VARCHAR(128) DEFAULT '' NOT NULL");
        $this->addSql("ALTER TABLE logs_incident ADD triggered_by VARCHAR(32) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE logs_incident ADD provider_reports JSON NOT NULL');
        $this->addSql("ALTER TABLE logs_incident ADD blocked_until_description VARCHAR(64) DEFAULT '' NOT NULL");

        $this->addSql('CREATE INDEX idx_incident_session_id ON logs_incident (session_id)');
        $this->addSql('CREATE INDEX idx_incident_triggered_by ON logs_incident (triggered_by)');
    }

    public function down(Schema $schema): void
    {
        // No reverse path: live-block reshape is destructive on purpose. Old
        // aggregation rows were dropped in up(); reverting the schema would
        // not restore them.
    }
}
