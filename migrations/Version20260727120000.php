<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the logs_suspicious_url table for admin-marked 404 URLs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE logs_suspicious_url (id INT AUTO_INCREMENT NOT NULL, url VARCHAR(2048) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE logs_suspicious_url');
    }
}
