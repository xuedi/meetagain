<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app_state table for persistent key-value runtime state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE app_state (id INT AUTO_INCREMENT NOT NULL, key_name VARCHAR(255) NOT NULL, value LONGTEXT NOT NULL, updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_app_state_key_name (key_name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_state');
    }
}
