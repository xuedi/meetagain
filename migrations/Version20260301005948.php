<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260301005948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend config.value column to 255 chars to support SEO meta descriptions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE config CHANGE value value VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE event CHANGE status status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE role role VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE config CHANGE value value VARCHAR(128) NOT NULL');
        $this->addSql('ALTER TABLE event CHANGE status status VARCHAR(20) DEFAULT \'draft\' NOT NULL');
        $this->addSql('ALTER TABLE `user` CHANGE role role VARCHAR(255) DEFAULT \'USER\' NOT NULL');
    }
}
