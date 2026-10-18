<?php declare(strict_types=1);

namespace ModuleTaskMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Task module - the run log of scheduled tasks and the failure transport of the message workers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE mod_task_run (id INT AUTO_INCREMENT NOT NULL, identifier VARCHAR(120) NOT NULL, started_at DATETIME NOT NULL, duration_ms INT NOT NULL, status VARCHAR(16) NOT NULL, message LONGTEXT NOT NULL, host VARCHAR(120) NOT NULL, INDEX idx_task_run_identifier_started (identifier, started_at), INDEX idx_task_run_started (started_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE mod_task_message (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_A3043C79FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mod_task_message');
        $this->addSql('DROP TABLE mod_task_run');
    }
}
