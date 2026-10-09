<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009154007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Moderation decisions keep a note, and a timed suspension stores when the block ends';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moderation_report ADD resolution_note LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD blocked_until DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moderation_report DROP resolution_note');
        $this->addSql('ALTER TABLE `user` DROP blocked_until');
    }
}
