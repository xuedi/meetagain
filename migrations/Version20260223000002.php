<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand regcode column to 64 chars for bin2hex(random_bytes(32)) tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` MODIFY regcode VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` MODIFY regcode VARCHAR(40) DEFAULT NULL');
    }
}
