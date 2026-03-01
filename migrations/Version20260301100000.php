<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260301100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add api_token_hash and api_token_created_at fields to user table for stateless Bearer token auth';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD api_token_hash VARCHAR(64) DEFAULT NULL, ADD api_token_created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP api_token_hash, DROP api_token_created_at');
    }
}
