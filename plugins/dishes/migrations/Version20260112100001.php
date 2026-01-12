<?php

declare(strict_types=1);

namespace PluginDishesMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260112100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add likes, suggestions, origin to dish; add recipe to dish_translation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dish ADD likes INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE dish ADD suggestions JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE dish ADD origin VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE dish_translation ADD recipe LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dish DROP likes');
        $this->addSql('ALTER TABLE dish DROP suggestions');
        $this->addSql('ALTER TABLE dish DROP origin');
        $this->addSql('ALTER TABLE dish_translation DROP recipe');
    }
}
