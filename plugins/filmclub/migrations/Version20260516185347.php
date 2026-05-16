<?php declare(strict_types=1);

namespace PluginFilmclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516185347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Filmclub settings become install-global: drop group_id and default_poll_duration_days, rename table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM filmclub_group_settings WHERE id NOT IN (
            SELECT min_id FROM (
                SELECT COALESCE(MIN(CASE WHEN group_id = 1 THEN id END), MIN(id)) AS min_id
                FROM filmclub_group_settings
            ) keep_row
        )');

        $this->addSql('ALTER TABLE filmclub_group_settings DROP INDEX unique_filmclub_group');
        $this->addSql('ALTER TABLE filmclub_group_settings DROP COLUMN group_id');
        $this->addSql('ALTER TABLE filmclub_group_settings DROP COLUMN default_poll_duration_days');
        $this->addSql('RENAME TABLE filmclub_group_settings TO filmclub_settings');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('RENAME TABLE filmclub_settings TO filmclub_group_settings');
        $this->addSql('ALTER TABLE filmclub_group_settings ADD COLUMN default_poll_duration_days INT NOT NULL DEFAULT 7');
        $this->addSql('ALTER TABLE filmclub_group_settings ADD COLUMN group_id INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE filmclub_group_settings ADD UNIQUE INDEX unique_filmclub_group (group_id)');
    }
}
