<?php declare(strict_types=1);

namespace PluginDinnerclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dish gallery image and image suggestion tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dinnerclub_dish_image (
            id INT AUTO_INCREMENT NOT NULL,
            dish_id INT NOT NULL,
            image_id INT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX unique_dish_image (dish_id, image_id),
            INDEX IDX_dinnerclub_dish_image_dish (dish_id),
            INDEX IDX_dinnerclub_dish_image_image (image_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_dinnerclub_dish_image_dish FOREIGN KEY (dish_id) REFERENCES dish (id) ON DELETE CASCADE,
            CONSTRAINT FK_dinnerclub_dish_image_image FOREIGN KEY (image_id) REFERENCES image (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE dinnerclub_dish_image_suggestion (
            id INT AUTO_INCREMENT NOT NULL,
            dish_id INT NOT NULL,
            image_id INT NOT NULL,
            type VARCHAR(20) NOT NULL,
            suggested_by INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_dinnerclub_dish_img_sug_dish (dish_id),
            INDEX IDX_dinnerclub_dish_img_sug_image (image_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_dinnerclub_dish_img_sug_dish FOREIGN KEY (dish_id) REFERENCES dish (id) ON DELETE CASCADE,
            CONSTRAINT FK_dinnerclub_dish_img_sug_image FOREIGN KEY (image_id) REFERENCES image (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dinnerclub_dish_image_suggestion');
        $this->addSql('DROP TABLE dinnerclub_dish_image');
    }
}
