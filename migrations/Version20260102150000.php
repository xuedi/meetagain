<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260102150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove title and content columns from announcement table - content now comes from linked CMS page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE announcement DROP title, DROP content');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE announcement ADD title VARCHAR(255) NOT NULL, ADD content LONGTEXT NOT NULL');
    }
}
