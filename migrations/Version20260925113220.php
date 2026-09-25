<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925113220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add item_report, the notices any visitor can file against a reportable item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE item_report (id INT AUTO_INCREMENT NOT NULL, item_type VARCHAR(64) NOT NULL, item_id INT NOT NULL, item_label VARCHAR(255) NOT NULL, reason VARCHAR(20) NOT NULL, relationship VARCHAR(20) NOT NULL, explanation LONGTEXT NOT NULL, notifier_name VARCHAR(255) NOT NULL, notifier_email VARCHAR(180) NOT NULL, good_faith TINYINT NOT NULL, status VARCHAR(10) NOT NULL, locale VARCHAR(5) NOT NULL, resolved_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, reporter_id INT DEFAULT NULL, resolved_by_id INT DEFAULT NULL, INDEX idx_item_report_item (item_type, item_id, status), INDEX IDX_42D6024BE1CFE6F5 (reporter_id), INDEX IDX_42D6024B6713A32B (resolved_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql('ALTER TABLE item_report ADD CONSTRAINT FK_42D6024BE1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE item_report ADD CONSTRAINT FK_42D6024B6713A32B FOREIGN KEY (resolved_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_report DROP FOREIGN KEY FK_42D6024BE1CFE6F5');
        $this->addSql('ALTER TABLE item_report DROP FOREIGN KEY FK_42D6024B6713A32B');
        $this->addSql('DROP TABLE item_report');
    }
}
