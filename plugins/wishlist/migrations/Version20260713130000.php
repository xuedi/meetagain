<?php

declare(strict_types=1);

namespace PluginWishlistMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Wishlist 1.0 - per-member item backlog keyed by item type and plain INT item id (generalized from filmclub wishlist)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plg_wishlist_entry (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            item_type VARCHAR(50) NOT NULL,
            item_id INT NOT NULL,
            priority_counter INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_wishlist_user_item (user_id, item_type, item_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE plg_wishlist_entry');
    }
}
