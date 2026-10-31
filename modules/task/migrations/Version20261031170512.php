<?php declare(strict_types=1);

namespace ModuleTaskMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031170512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Task module - requests of manual actions and their remembered settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE mod_task_action_request (id INT AUTO_INCREMENT NOT NULL, state VARCHAR(16) NOT NULL, started_at DATETIME DEFAULT NULL, lease_until DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, status VARCHAR(16) DEFAULT NULL, message LONGTEXT DEFAULT NULL, identifier VARCHAR(120) NOT NULL, action_values JSON NOT NULL, requested_by VARCHAR(180) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_task_action_request_identifier (identifier, id), INDEX idx_task_action_request_state (state, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE mod_task_action_setting (id INT AUTO_INCREMENT NOT NULL, identifier VARCHAR(120) NOT NULL, setting_key VARCHAR(64) NOT NULL, setting_value JSON DEFAULT NULL, UNIQUE INDEX uniq_task_action_setting (identifier, setting_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mod_task_action_setting');
        $this->addSql('DROP TABLE mod_task_action_request');
    }
}
