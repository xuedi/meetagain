<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109001041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A guest support thread logs one row per view';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE support_request_view (id INT AUTO_INCREMENT NOT NULL, seen_at DATETIME NOT NULL, support_request_id INT NOT NULL, INDEX idx_support_request_view_seen (support_request_id, seen_at), INDEX IDX_BAE1EE4960CA7C87 (support_request_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'ALTER TABLE support_request_view ADD CONSTRAINT FK_BAE1EE4960CA7C87 FOREIGN KEY (support_request_id) REFERENCES support_request (id) ON DELETE CASCADE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE support_request_view DROP FOREIGN KEY FK_BAE1EE4960CA7C87');
        $this->addSql('DROP TABLE support_request_view');
    }
}
