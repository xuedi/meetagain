<?php declare(strict_types=1);

namespace PluginFilmsMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Films schema drift - drop the DBAL 3 type comments, so the schema matches the mapping; column types are unchanged';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_films_film CHANGE created_at created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE plg_films_film CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }
}
