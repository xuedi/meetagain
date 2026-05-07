<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename not_found_log to logs_not_found, widen url to 2048, add user_agent and referer columns.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE not_found_log TO logs_not_found');
        $this->addSql('ALTER TABLE logs_not_found MODIFY url VARCHAR(2048) NOT NULL');
        $this->addSql('ALTER TABLE logs_not_found MODIFY ip VARCHAR(45) NOT NULL');
        $this->addSql('ALTER TABLE logs_not_found ADD user_agent VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE logs_not_found ADD referer VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE logs_not_found DROP referer');
        $this->addSql('ALTER TABLE logs_not_found DROP user_agent');
        $this->addSql('ALTER TABLE logs_not_found MODIFY ip VARCHAR(16) NOT NULL');
        $this->addSql('ALTER TABLE logs_not_found MODIFY url VARCHAR(255) NOT NULL');
        $this->addSql('RENAME TABLE logs_not_found TO not_found_log');
    }
}
