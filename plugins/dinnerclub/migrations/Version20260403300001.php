<?php declare(strict_types=1);

namespace PluginDinnerclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403300001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dish_like table for per-user like tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dish_like (
            id INT AUTO_INCREMENT NOT NULL,
            dish_id INT NOT NULL,
            user_id INT NOT NULL,
            UNIQUE INDEX unique_dish_user_like (dish_id, user_id),
            INDEX IDX_dish_like_dish (dish_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_dish_like_dish FOREIGN KEY (dish_id) REFERENCES dish (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dish_like');
    }
}
