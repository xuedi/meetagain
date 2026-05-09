<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop logs_url_probing_incident, create logs_incident with multi-source counters, reset aggregator watermarks.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS logs_url_probing_incident');

        $this->addSql(<<<'SQL'
            CREATE TABLE logs_incident (
                id INT AUTO_INCREMENT NOT NULL,
                ip VARCHAR(45) NOT NULL,
                started_at DATETIME NOT NULL,
                ended_at DATETIME NOT NULL,
                probing_hits INT DEFAULT 0 NOT NULL,
                access_denied_hits INT DEFAULT 0 NOT NULL,
                rate_limit_hits INT DEFAULT 0 NOT NULL,
                brute_force_hits INT DEFAULT 0 NOT NULL,
                total_hits INT DEFAULT 0 NOT NULL,
                distinct_paths INT DEFAULT 0 NOT NULL,
                distinct_user_agents INT DEFAULT 0 NOT NULL,
                user_agent VARCHAR(512) DEFAULT NULL,
                sample_urls JSON NOT NULL,
                severity VARCHAR(16) DEFAULT 'low' NOT NULL,
                country_code VARCHAR(2) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_incident_ip (ip),
                INDEX idx_incident_started_at (started_at),
                INDEX idx_incident_ended_at (ended_at),
                INDEX idx_incident_ip_ended_at (ip, ended_at),
                INDEX idx_incident_severity (severity),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            DELETE FROM app_state WHERE key_name IN (
                'security.url_probing.last_processed_log_id',
                'cron.url_probing_aggregator.last_run',
                'security.incident.url_probing.last_processed_log_id',
                'security.incident.access_denied.last_processed_log_id',
                'security.incident.rate_limit.last_processed_log_id',
                'security.incident.brute_force.last_processed_log_id',
                'cron.incident_aggregator.last_run'
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE logs_incident');

        $this->addSql(<<<'SQL'
            CREATE TABLE logs_url_probing_incident (
                id INT AUTO_INCREMENT NOT NULL,
                ip VARCHAR(45) NOT NULL,
                started_at DATETIME NOT NULL,
                ended_at DATETIME NOT NULL,
                probe_count INT NOT NULL,
                distinct_url_count INT NOT NULL,
                user_agent VARCHAR(512) DEFAULT NULL,
                sample_urls JSON NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_url_probing_ip (ip),
                INDEX idx_url_probing_started_at (started_at),
                INDEX idx_url_probing_ip_ended_at (ip, ended_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }
}
