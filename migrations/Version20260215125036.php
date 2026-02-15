<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260215125036 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete all Title blocks from cms_block table as CmsTitle entities are now used';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM cms_block WHERE type = 10');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('-- Title blocks deleted, cannot restore');
    }
}
