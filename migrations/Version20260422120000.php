<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create email_blocklist table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_blocklist (id INT AUTO_INCREMENT NOT NULL, added_by_id INT DEFAULT NULL, email VARCHAR(255) NOT NULL, reason VARCHAR(255) NOT NULL, added_at DATETIME NOT NULL, UNIQUE INDEX uniq_email_blocklist_email (email), INDEX IDX_6423B7F355B127A4 (added_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE email_blocklist ADD CONSTRAINT FK_6423B7F355B127A4 FOREIGN KEY (added_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_blocklist DROP FOREIGN KEY FK_6423B7F355B127A4');
        $this->addSql('DROP TABLE email_blocklist');
    }
}
