<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404230001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image_report table for tracking reported images';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE image_report (id INT AUTO_INCREMENT NOT NULL, image_id INT DEFAULT NULL, reporter_id INT DEFAULT NULL, reason INT NOT NULL, remarks LONGTEXT DEFAULT NULL, status VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_6B32294C3DA5256D (image_id), INDEX IDX_6B32294CE1CFE6F5 (reporter_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE image_report ADD CONSTRAINT FK_6B32294C3DA5256D FOREIGN KEY (image_id) REFERENCES image (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE image_report ADD CONSTRAINT FK_6B32294CE1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image_report DROP FOREIGN KEY FK_6B32294C3DA5256D');
        $this->addSql('ALTER TABLE image_report DROP FOREIGN KEY FK_6B32294CE1CFE6F5');
        $this->addSql('DROP TABLE image_report');
    }
}
