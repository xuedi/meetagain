<?php declare(strict_types=1);

namespace PluginPhotosMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Photos: a per-photo flag queueing it for the next contest.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_photos_photo ADD contest_submitted TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_photos_photo DROP contest_submitted');
    }
}
