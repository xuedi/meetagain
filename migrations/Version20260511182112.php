<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511182112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable incident_id FK to logs_not_found and logs_access_denied';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE logs_access_denied ADD incident_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE logs_access_denied ADD CONSTRAINT FK_CB682DDE59E53FB9 FOREIGN KEY (incident_id) REFERENCES logs_incident (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CB682DDE59E53FB9 ON logs_access_denied (incident_id)');
        $this->addSql('ALTER TABLE logs_not_found ADD incident_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE logs_not_found ADD CONSTRAINT FK_C33C8E4859E53FB9 FOREIGN KEY (incident_id) REFERENCES logs_incident (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C33C8E4859E53FB9 ON logs_not_found (incident_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE logs_access_denied DROP FOREIGN KEY FK_CB682DDE59E53FB9');
        $this->addSql('DROP INDEX IDX_CB682DDE59E53FB9 ON logs_access_denied');
        $this->addSql('ALTER TABLE logs_access_denied DROP incident_id');
        $this->addSql('ALTER TABLE logs_not_found DROP FOREIGN KEY FK_C33C8E4859E53FB9');
        $this->addSql('DROP INDEX IDX_C33C8E4859E53FB9 ON logs_not_found');
        $this->addSql('ALTER TABLE logs_not_found DROP incident_id');
    }
}
