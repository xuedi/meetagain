<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260101170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add command_execution_log table for tracking command executions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE command_execution_log (id INT AUTO_INCREMENT NOT NULL, command_name VARCHAR(100) NOT NULL, started_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, status VARCHAR(20) NOT NULL, exit_code INT DEFAULT NULL, output LONGTEXT DEFAULT NULL, error_output LONGTEXT DEFAULT NULL, triggered_by VARCHAR(20) NOT NULL, INDEX idx_command_log_name (command_name), INDEX idx_command_log_started (started_at), INDEX idx_command_log_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE command_execution_log');
    }
}
