<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260318165055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'added config settings for admin notification';
    }

    //
    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO config (name, value, type) VALUES ('send_admin_notification', 'true', 'boolean')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM config WHERE name = 'send_admin_notification'");
    }
}
