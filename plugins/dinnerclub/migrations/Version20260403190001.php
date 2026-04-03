<?php declare(strict_types=1);

namespace PluginDinnerclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403190001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legacy datetime_immutable COMMENTs, drop redundant IDX_dinner_event index, and align index names to Doctrine standard for dinnerclub tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_dinner_event ON dinner');
        $this->addSql('ALTER TABLE dinner CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE dinner_course RENAME INDEX idx_dinner_course_dinner TO IDX_33092C6CC8B1AA0C');
        $this->addSql('ALTER TABLE dinner_course_item RENAME INDEX idx_dinner_course_item_course TO IDX_FA28170591CC992');
        $this->addSql('ALTER TABLE dinner_course_item RENAME INDEX idx_dinner_course_item_dish TO IDX_FA28170148EB0CB');
        $this->addSql('ALTER TABLE dish CHANGE likes likes INT NOT NULL');
        $this->addSql('ALTER TABLE dish_like RENAME INDEX idx_dish_like_dish TO IDX_2661FFA2148EB0CB');
        $this->addSql('ALTER TABLE dish_list CHANGE created_at created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dinner CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_dinner_event ON dinner (event_id)');
        $this->addSql('ALTER TABLE dinner_course RENAME INDEX idx_33092c6cc8b1aa0c TO IDX_dinner_course_dinner');
        $this->addSql('ALTER TABLE dinner_course_item RENAME INDEX idx_fa28170591cc992 TO IDX_dinner_course_item_course');
        $this->addSql('ALTER TABLE dinner_course_item RENAME INDEX idx_fa28170148eb0cb TO IDX_dinner_course_item_dish');
        $this->addSql('ALTER TABLE dish CHANGE likes likes INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE dish_like RENAME INDEX idx_2661ffa2148eb0cb TO IDX_dish_like_dish');
        $this->addSql('ALTER TABLE dish_list CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
