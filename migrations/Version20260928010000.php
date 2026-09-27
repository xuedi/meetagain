<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Core schema drift - drop the DBAL 3 type comments and the empty-string column defaults the entity does not declare, so the schema matches the mapping; column types are unchanged';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE logs_security_measure CHANGE day day DATE NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE app_state CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE message CHANGE edited_at edited_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE event CHANGE event_reminder_sent_at event_reminder_sent_at DATETIME DEFAULT NULL');
        $this->addSql(
            'ALTER TABLE logs_incident CHANGE session_id session_id VARCHAR(128) NOT NULL, CHANGE triggered_by triggered_by VARCHAR(32) NOT NULL, CHANGE blocked_until_description blocked_until_description VARCHAR(64) NOT NULL',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE logs_security_measure CHANGE day day DATE NOT NULL COMMENT '(DC2Type:date_immutable)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'",
        );
        $this->addSql("ALTER TABLE app_state CHANGE updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("ALTER TABLE message CHANGE edited_at edited_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("ALTER TABLE event CHANGE event_reminder_sent_at event_reminder_sent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql(
            "ALTER TABLE logs_incident CHANGE session_id session_id VARCHAR(128) NOT NULL DEFAULT '', CHANGE triggered_by triggered_by VARCHAR(32) NOT NULL DEFAULT '', CHANGE blocked_until_description blocked_until_description VARCHAR(64) NOT NULL DEFAULT ''",
        );
    }
}
