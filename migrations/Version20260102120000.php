<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260102120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add link_hash column to announcement table for stable email links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE announcement ADD link_hash VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4DB9D91C1FC998E4 ON announcement (link_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_4DB9D91C1FC998E4 ON announcement');
        $this->addSql('ALTER TABLE announcement DROP link_hash');
    }
}
