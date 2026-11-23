<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drops two leftover tables no release maps: a hand-made comment backup and the old filmclub settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS comment_backup_20260801');
        $this->addSql('DROP TABLE IF EXISTS filmclub_settings');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The dropped rows are not kept anywhere.');
    }
}
