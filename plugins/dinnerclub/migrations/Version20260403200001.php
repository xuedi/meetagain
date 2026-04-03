<?php declare(strict_types=1);

namespace PluginDinnerclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403200001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reservation_name column to dinner table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dinner ADD reservation_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dinner DROP COLUMN reservation_name');
    }
}
