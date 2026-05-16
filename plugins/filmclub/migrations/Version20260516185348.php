<?php declare(strict_types=1);

namespace PluginFilmclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516185348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add duration_days column to film_poll, backfilled from existing end_date - start_date difference';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE film_poll ADD COLUMN duration_days INT NOT NULL DEFAULT 7');
        $this->addSql('UPDATE film_poll SET duration_days = GREATEST(1, DATEDIFF(end_date, created_at))');
        $this->addSql('ALTER TABLE film_poll ALTER COLUMN duration_days DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE film_poll DROP COLUMN duration_days');
    }
}
