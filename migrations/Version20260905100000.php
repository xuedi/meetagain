<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Item tags: a managed flag marking rows a plugin owns rather than a person.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_tag ADD managed TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_tag DROP managed');
    }
}
