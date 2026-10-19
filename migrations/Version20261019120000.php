<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop cron_log: the task runs replaced it';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE cron_log');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE cron_log (id INT AUTO_INCREMENT NOT NULL, run_at DATETIME NOT NULL, status VARCHAR(16) NOT NULL, tasks JSON NOT NULL, duration_ms INT NOT NULL, INDEX IDX_7C0163B3518D64A8 (run_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
    }
}
