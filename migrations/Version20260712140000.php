<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create plugin_settings table: generic global-scope JSON store for plugin settings descriptors';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE plugin_settings (
                id INT AUTO_INCREMENT NOT NULL,
                plugin_key VARCHAR(100) NOT NULL,
                data JSON NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX unique_plugin_key (plugin_key),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE plugin_settings
        SQL);
    }
}
