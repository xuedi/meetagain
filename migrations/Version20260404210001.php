<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404210001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Merge PluginDishGallery (6) into PluginDish (5) image type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE image SET type = 5 WHERE type = 6');
    }

    public function down(Schema $schema): void
    {
        // Cannot distinguish former preview images from gallery images after merging
        $this->addSql('SELECT 1');
    }
}
