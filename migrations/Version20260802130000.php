<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the wall_reply and wall_post tables; the town hall wall was replaced by the forum';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE wall_reply');
        $this->addSql('DROP TABLE wall_post');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE wall_post (
                id INT AUTO_INCREMENT NOT NULL,
                author_id INT DEFAULT NULL,
                content LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                edited_at DATETIME DEFAULT NULL,
                INDEX IDX_WALL_POST_AUTHOR (author_id),
                INDEX IDX_WALL_POST_CREATED (created_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE wall_reply (
                id INT AUTO_INCREMENT NOT NULL,
                post_id INT NOT NULL,
                author_id INT DEFAULT NULL,
                content LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_WALL_REPLY_POST (post_id),
                INDEX IDX_WALL_REPLY_AUTHOR (author_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE wall_post ADD CONSTRAINT FK_WALL_POST_AUTHOR FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wall_reply ADD CONSTRAINT FK_WALL_REPLY_POST FOREIGN KEY (post_id) REFERENCES wall_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wall_reply ADD CONSTRAINT FK_WALL_REPLY_AUTHOR FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }
}
