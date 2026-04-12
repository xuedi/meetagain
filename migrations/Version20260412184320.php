<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412184320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cron_log table for structured cron run logging';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cron_log (id INT AUTO_INCREMENT NOT NULL, run_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(16) NOT NULL, tasks JSON NOT NULL, INDEX IDX_cron_log_run_at (run_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cron_log');
    }
}
