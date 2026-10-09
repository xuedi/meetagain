<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009114747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'moderation_report holds member reports about members, comments and messages, keyed by subject type and id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE moderation_report (id INT AUTO_INCREMENT NOT NULL, subject_type VARCHAR(64) NOT NULL, subject_id INT NOT NULL, subject_label VARCHAR(255) NOT NULL, excerpt LONGTEXT DEFAULT NULL, reason VARCHAR(32) NOT NULL, remarks LONGTEXT DEFAULT NULL, status VARCHAR(16) NOT NULL, resolved_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, author_id INT DEFAULT NULL, reporter_id INT DEFAULT NULL, resolved_by_id INT DEFAULT NULL, INDEX idx_moderation_report_subject (subject_type, subject_id), INDEX idx_moderation_report_status (status), INDEX IDX_33BE7A3EF675F31B (author_id), INDEX IDX_33BE7A3EE1CFE6F5 (reporter_id), INDEX IDX_33BE7A3E6713A32B (resolved_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql('ALTER TABLE moderation_report ADD CONSTRAINT FK_33BE7A3EF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE moderation_report ADD CONSTRAINT FK_33BE7A3EE1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql(
            'ALTER TABLE moderation_report ADD CONSTRAINT FK_33BE7A3E6713A32B FOREIGN KEY (resolved_by_id) REFERENCES `user` (id) ON DELETE SET NULL',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE moderation_report DROP FOREIGN KEY FK_33BE7A3EF675F31B');
        $this->addSql('ALTER TABLE moderation_report DROP FOREIGN KEY FK_33BE7A3EE1CFE6F5');
        $this->addSql('ALTER TABLE moderation_report DROP FOREIGN KEY FK_33BE7A3E6713A32B');
        $this->addSql('DROP TABLE moderation_report');
    }
}
