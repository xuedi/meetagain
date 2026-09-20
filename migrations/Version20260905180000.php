<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the suggestion table for the universal propose-a-new-row tool';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE suggestion (id INT AUTO_INCREMENT NOT NULL, target_type VARCHAR(50) NOT NULL, payload JSON NOT NULL, status VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, created_id INT DEFAULT NULL, proposed_by_id INT NOT NULL, reviewed_by_id INT DEFAULT NULL, INDEX IDX_DD80F31BDAB5A938 (proposed_by_id), INDEX IDX_DD80F31BFC6B21F1 (reviewed_by_id), INDEX idx_suggestion_target_type (target_type), INDEX idx_suggestion_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql('ALTER TABLE suggestion ADD CONSTRAINT FK_DD80F31BDAB5A938 FOREIGN KEY (proposed_by_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE suggestion ADD CONSTRAINT FK_DD80F31BFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE suggestion DROP FOREIGN KEY FK_DD80F31BDAB5A938');
        $this->addSql('ALTER TABLE suggestion DROP FOREIGN KEY FK_DD80F31BFC6B21F1');
        $this->addSql('DROP TABLE suggestion');
    }
}
