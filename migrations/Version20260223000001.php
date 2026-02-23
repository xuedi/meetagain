<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add regcode_expires_at to user table for token expiry enforcement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD regcode_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP regcode_expires_at');
    }
}
