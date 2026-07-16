<?php

declare(strict_types=1);

namespace PluginDishesMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the dishes schema. Tables are prefixed `dishes_` so they do not collide with the old
 * dinnerclub tables (`dish`, `dish_translation`, `dinnerclub_dish_image`), which stay in place on
 * production until the MigrateDinnerclubToDishes hotfix has copied their data; a later follow-up
 * migration drops the old tables. On a fresh install the old tables never existed.
 */
final class Version20260713150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dishes 1.0 - dish catalog with translations, gallery, likes (new schema alongside old dinnerclub tables)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dishes_dish (
            id INT AUTO_INCREMENT NOT NULL,
            preview_image_id INT DEFAULT NULL,
            pronunciation_system_id INT DEFAULT NULL,
            phonetic VARCHAR(255) DEFAULT NULL,
            likes INT NOT NULL,
            origin VARCHAR(255) DEFAULT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_361A2861FAE957CD (preview_image_id),
            INDEX IDX_361A2861732B7AF4 (pronunciation_system_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE dishes_dish_translation (
            id INT AUTO_INCREMENT NOT NULL,
            dish_id INT NOT NULL,
            language VARCHAR(2) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT NOT NULL,
            recipe LONGTEXT DEFAULT NULL,
            INDEX IDX_8DB111D148EB0CB (dish_id),
            UNIQUE INDEX uniq_dishes_translation_lang_dish (language, dish_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE dishes_dish_image (
            id INT AUTO_INCREMENT NOT NULL,
            dish_id INT NOT NULL,
            image_id INT NOT NULL,
            sort_order INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_625E7E20148EB0CB (dish_id),
            INDEX IDX_625E7E203DA5256D (image_id),
            UNIQUE INDEX uniq_dishes_dish_image (dish_id, image_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE dishes_dish_like (
            id INT AUTO_INCREMENT NOT NULL,
            dish_id INT NOT NULL,
            user_id INT NOT NULL,
            INDEX IDX_A83F5E57148EB0CB (dish_id),
            UNIQUE INDEX uniq_dishes_dish_user_like (dish_id, user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE dishes_dish ADD CONSTRAINT FK_361A2861FAE957CD FOREIGN KEY (preview_image_id) REFERENCES image (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE dishes_dish ADD CONSTRAINT FK_361A2861732B7AF4 FOREIGN KEY (pronunciation_system_id) REFERENCES pronunciation_system (id)');
        $this->addSql('ALTER TABLE dishes_dish_translation ADD CONSTRAINT FK_8DB111D148EB0CB FOREIGN KEY (dish_id) REFERENCES dishes_dish (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dishes_dish_image ADD CONSTRAINT FK_625E7E20148EB0CB FOREIGN KEY (dish_id) REFERENCES dishes_dish (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dishes_dish_image ADD CONSTRAINT FK_625E7E203DA5256D FOREIGN KEY (image_id) REFERENCES image (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dishes_dish_like ADD CONSTRAINT FK_A83F5E57148EB0CB FOREIGN KEY (dish_id) REFERENCES dishes_dish (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dishes_dish_translation DROP FOREIGN KEY FK_8DB111D148EB0CB');
        $this->addSql('ALTER TABLE dishes_dish_image DROP FOREIGN KEY FK_625E7E20148EB0CB');
        $this->addSql('ALTER TABLE dishes_dish_image DROP FOREIGN KEY FK_625E7E203DA5256D');
        $this->addSql('ALTER TABLE dishes_dish_like DROP FOREIGN KEY FK_A83F5E57148EB0CB');
        $this->addSql('ALTER TABLE dishes_dish DROP FOREIGN KEY FK_361A2861FAE957CD');
        $this->addSql('ALTER TABLE dishes_dish DROP FOREIGN KEY FK_361A2861732B7AF4');
        $this->addSql('DROP TABLE dishes_dish_like');
        $this->addSql('DROP TABLE dishes_dish_image');
        $this->addSql('DROP TABLE dishes_dish_translation');
        $this->addSql('DROP TABLE dishes_dish');
    }
}
