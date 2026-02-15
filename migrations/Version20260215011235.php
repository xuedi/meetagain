<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260215011235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add locked flag to CMS pages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms ADD locked TINYINT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cms DROP locked');
    }
}
