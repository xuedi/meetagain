<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919141426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable incident_id FK to logs_security_measure';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE logs_security_measure ADD incident_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE logs_security_measure ADD CONSTRAINT FK_C923EE3F59E53FB9 FOREIGN KEY (incident_id) REFERENCES logs_incident (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C923EE3F59E53FB9 ON logs_security_measure (incident_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE logs_security_measure DROP FOREIGN KEY FK_C923EE3F59E53FB9');
        $this->addSql('DROP INDEX IDX_C923EE3F59E53FB9 ON logs_security_measure');
        $this->addSql('ALTER TABLE logs_security_measure DROP incident_id');
    }
}
