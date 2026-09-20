<?php declare(strict_types=1);

namespace ModuleBallotMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ballot module - a caller-supplied title, so a ballot page says what is being decided';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_ballot ADD title VARCHAR(191) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_ballot DROP title');
    }
}
