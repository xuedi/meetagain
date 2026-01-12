<?php

declare(strict_types=1);

namespace PluginDishesMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260112100002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create dish_list table for user collections';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dish_list (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            is_public TINYINT(1) NOT NULL,
            dish_ids JSON NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dish_list');
    }
}
