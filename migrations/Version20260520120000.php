<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create wall_post and wall_reply tables and seed show_town_hall config';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE wall_post (
                id INT AUTO_INCREMENT NOT NULL,
                author_id INT DEFAULT NULL,
                content LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                edited_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_WALL_POST_AUTHOR (author_id),
                INDEX IDX_WALL_POST_CREATED (created_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE wall_post
                ADD CONSTRAINT FK_WALL_POST_AUTHOR
                    FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE SET NULL
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE wall_reply (
                id INT AUTO_INCREMENT NOT NULL,
                post_id INT NOT NULL,
                author_id INT DEFAULT NULL,
                content LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_WALL_REPLY_POST (post_id),
                INDEX IDX_WALL_REPLY_AUTHOR (author_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE wall_reply
                ADD CONSTRAINT FK_WALL_REPLY_POST
                    FOREIGN KEY (post_id) REFERENCES wall_post (id) ON DELETE CASCADE,
                ADD CONSTRAINT FK_WALL_REPLY_AUTHOR
                    FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE SET NULL
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO config (name, value, type) VALUES ('show_town_hall', 'false', 'boolean')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM config WHERE name = 'show_town_hall'");
        $this->addSql('ALTER TABLE wall_reply DROP FOREIGN KEY FK_WALL_REPLY_POST');
        $this->addSql('ALTER TABLE wall_reply DROP FOREIGN KEY FK_WALL_REPLY_AUTHOR');
        $this->addSql('DROP TABLE wall_reply');
        $this->addSql('ALTER TABLE wall_post DROP FOREIGN KEY FK_WALL_POST_AUTHOR');
        $this->addSql('DROP TABLE wall_post');
    }
}
