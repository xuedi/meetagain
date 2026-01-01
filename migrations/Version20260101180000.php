<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop login_attempt table - login attempt tracking removed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS login_attempt');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE login_attempt (id INT AUTO_INCREMENT NOT NULL, attempted_at DATETIME NOT NULL, successful TINYINT NOT NULL, ip VARCHAR(45) NOT NULL, user_agent VARCHAR(255) DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_8C11C1BA76ED395 (user_id), INDEX idx_login_attempt_time (attempted_at), INDEX idx_login_attempt_ip (ip), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE login_attempt ADD CONSTRAINT FK_8C11C1BA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }
}
