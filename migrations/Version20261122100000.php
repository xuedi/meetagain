<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A member remembers when they asked to be deleted, so the erasure runs after its window';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD deletion_requested_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP deletion_requested_at');
    }
}
