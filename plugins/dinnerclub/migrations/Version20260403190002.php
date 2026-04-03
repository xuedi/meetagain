<?php declare(strict_types=1);

namespace PluginDinnerclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403190002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove ON DELETE CASCADE from dinner_course_item.dish_id — dish is a shared entity, deleting a dish should be restricted not cascade into dinner history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dinner_course_item DROP FOREIGN KEY `FK_dinner_course_item_dish`');
        $this->addSql('ALTER TABLE dinner_course_item ADD CONSTRAINT FK_FA28170148EB0CB FOREIGN KEY (dish_id) REFERENCES dish (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dinner_course_item DROP FOREIGN KEY FK_FA28170148EB0CB');
        $this->addSql('ALTER TABLE dinner_course_item ADD CONSTRAINT `FK_dinner_course_item_dish` FOREIGN KEY (dish_id) REFERENCES dish (id) ON DELETE CASCADE');
    }
}
