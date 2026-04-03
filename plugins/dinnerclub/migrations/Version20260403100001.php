<?php declare(strict_types=1);

namespace PluginDinnerclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add price_per_person column to dinner table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dinner ADD price_per_person NUMERIC(8, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dinner DROP COLUMN price_per_person');
    }
}
