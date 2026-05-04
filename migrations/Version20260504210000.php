<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop legacy API token columns from user table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP api_token_hash, DROP api_token_created_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD api_token_hash VARCHAR(64) DEFAULT NULL, ADD api_token_created_at DATETIME DEFAULT NULL');
    }
}
