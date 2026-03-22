<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate Paragraph blocks (type=5) to Text blocks (type=2) — JSON is compatible';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE cms_block SET type = 2 WHERE type = 5');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE cms_block SET type = 5 WHERE type = 2');
    }
}
