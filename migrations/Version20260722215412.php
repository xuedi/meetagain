<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The universal change-proposal table: a polymorphic target reference (target_type + target_id,
 * no FK - plugin tables must not be referenced from core), the proposer/reviewer, a status, and
 * the before/after field map as JSON. Proposals die with their proposer (CASCADE) but keep the
 * audit row when the reviewer account goes (SET NULL).
 */
final class Version20260722215412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the change_proposal table for the universal review and change-suggestion tool';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE change_proposal (id INT AUTO_INCREMENT NOT NULL, target_type VARCHAR(50) NOT NULL, target_id INT NOT NULL, status VARCHAR(10) NOT NULL, changes JSON NOT NULL, created_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, proposed_by_id INT NOT NULL, reviewed_by_id INT DEFAULT NULL, INDEX IDX_101F889CDAB5A938 (proposed_by_id), INDEX IDX_101F889CFC6B21F1 (reviewed_by_id), INDEX idx_change_proposal_target (target_type, target_id), INDEX idx_change_proposal_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE change_proposal ADD CONSTRAINT FK_101F889CDAB5A938 FOREIGN KEY (proposed_by_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE change_proposal ADD CONSTRAINT FK_101F889CFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE change_proposal DROP FOREIGN KEY FK_101F889CDAB5A938');
        $this->addSql('ALTER TABLE change_proposal DROP FOREIGN KEY FK_101F889CFC6B21F1');
        $this->addSql('DROP TABLE change_proposal');
    }
}
