<?php

declare(strict_types=1);

namespace PluginDinnerclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create dinner, dinner_course, dinner_course_item tables for event menu cards';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dinner (
            id INT AUTO_INCREMENT NOT NULL,
            event_id INT NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX unique_dinner_event (event_id),
            INDEX IDX_dinner_event (event_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE dinner_course (
            id INT AUTO_INCREMENT NOT NULL,
            dinner_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL,
            INDEX IDX_dinner_course_dinner (dinner_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_dinner_course_dinner FOREIGN KEY (dinner_id) REFERENCES dinner (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE dinner_course_item (
            id INT AUTO_INCREMENT NOT NULL,
            course_id INT NOT NULL,
            dish_id INT NOT NULL,
            is_primary TINYINT(1) NOT NULL,
            sort_order INT NOT NULL,
            UNIQUE INDEX unique_course_dish (course_id, dish_id),
            INDEX IDX_dinner_course_item_course (course_id),
            INDEX IDX_dinner_course_item_dish (dish_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_dinner_course_item_course FOREIGN KEY (course_id) REFERENCES dinner_course (id) ON DELETE CASCADE,
            CONSTRAINT FK_dinner_course_item_dish FOREIGN KEY (dish_id) REFERENCES dish (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE dinner ADD CONSTRAINT FK_dinner_event FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dinner DROP FOREIGN KEY FK_dinner_event');
        $this->addSql('DROP TABLE dinner_course_item');
        $this->addSql('DROP TABLE dinner_course');
        $this->addSql('DROP TABLE dinner');
    }
}
