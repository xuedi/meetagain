<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260822120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the cms.email_footer flag that decides which pages are linked from the footer of every email, backfilled from the current fourth bottom-menu column so existing mail keeps its links.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms ADD email_footer TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE cms SET email_footer = 1 WHERE id IN (SELECT cms_id FROM cms_menu_location WHERE location = 4)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms DROP email_footer');
    }
}
