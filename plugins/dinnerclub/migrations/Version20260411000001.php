<?php declare(strict_types=1);

namespace PluginDinnerclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename locale code "cn" to "zh" in dinnerclub tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE dish_translation SET language='zh' WHERE language='cn'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE dish_translation SET language='cn' WHERE language='zh'");
    }
}
