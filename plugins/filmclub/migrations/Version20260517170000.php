<?php declare(strict_types=1);

namespace PluginFilmclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Filmclub - drop the approved column from film table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE film DROP COLUMN approved');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE film ADD approved TINYINT(1) NOT NULL');
    }
}
