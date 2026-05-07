<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create logs_url_probing_incident, logs_access_denied, logs_rate_limit tables.';
    }

    public function up(Schema $schema): void
    {
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

        $this->addSql(<<<'SQL'
            CREATE TABLE logs_access_denied (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                ip VARCHAR(45) NOT NULL,
                url VARCHAR(2048) NOT NULL,
                reason VARCHAR(64) NOT NULL,
                user_agent VARCHAR(512) DEFAULT NULL,
                INDEX idx_access_denied_created_at (created_at),
                INDEX idx_access_denied_ip_created_at (ip, created_at),
                INDEX IDX_CB682DDEA76ED395 (user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE logs_access_denied
                ADD CONSTRAINT FK_access_denied_user FOREIGN KEY (user_id)
                REFERENCES `user` (id) ON DELETE SET NULL
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE logs_rate_limit (
                id INT AUTO_INCREMENT NOT NULL,
                created_at DATETIME NOT NULL,
                ip VARCHAR(45) NOT NULL,
                url VARCHAR(2048) NOT NULL,
                limiter VARCHAR(64) NOT NULL,
                user_identifier VARCHAR(255) DEFAULT NULL,
                user_agent VARCHAR(512) DEFAULT NULL,
                INDEX idx_rate_limit_created_at (created_at),
                INDEX idx_rate_limit_ip_created_at (ip, created_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE logs_access_denied DROP FOREIGN KEY FK_access_denied_user');
        $this->addSql('DROP TABLE logs_rate_limit');
        $this->addSql('DROP TABLE logs_access_denied');
        $this->addSql('DROP TABLE logs_url_probing_incident');
    }
}
