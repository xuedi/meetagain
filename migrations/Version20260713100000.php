<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create event_item_association: the universal event-to-item link (item_type + plain INT item_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE event_item_association (
                id INT AUTO_INCREMENT NOT NULL,
                event_id INT NOT NULL,
                item_type VARCHAR(50) NOT NULL,
                item_id INT NOT NULL,
                created_by INT NOT NULL,
                created_at DATETIME NOT NULL,
                position INT DEFAULT NULL,
                section_label VARCHAR(100) DEFAULT NULL,
                UNIQUE INDEX unique_event_item (event_id, item_type, item_id),
                INDEX IDX_3BFD9A3271F7E88B (event_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql('ALTER TABLE event_item_association ADD CONSTRAINT FK_3BFD9A3271F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_item_association DROP FOREIGN KEY FK_3BFD9A3271F7E88B');
        $this->addSql(<<<'SQL'
            DROP TABLE event_item_association
        SQL);
    }
}
