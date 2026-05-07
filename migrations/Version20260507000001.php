<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create developer_app_application table for OAuth client self-service registrations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE developer_app_application (
                id INT AUTO_INCREMENT NOT NULL,
                submitted_by_id INT NOT NULL,
                reviewed_by_id INT DEFAULT NULL,
                logo_image_id INT DEFAULT NULL,
                app_name VARCHAR(80) NOT NULL,
                description VARCHAR(500) DEFAULT NULL,
                homepage_url VARCHAR(255) DEFAULT NULL,
                redirect_uris JSON NOT NULL,
                requested_grants JSON NOT NULL,
                requested_scopes JSON NOT NULL,
                status VARCHAR(255) NOT NULL,
                submitted_at DATETIME NOT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                deny_reason LONGTEXT DEFAULT NULL,
                client_identifier VARCHAR(80) DEFAULT NULL,
                user_read_outcome TINYINT(1) NOT NULL,
                INDEX idx_developer_app_status_submitted (status, submitted_at),
                INDEX idx_developer_app_user_submitted (submitted_by_id, submitted_at),
                INDEX idx_developer_app_client_identifier (client_identifier),
                INDEX IDX_6B872D95FC6B21F1 (reviewed_by_id),
                INDEX IDX_6B872D956D947EBB (logo_image_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE developer_app_application
                ADD CONSTRAINT FK_developer_app_submitted_by FOREIGN KEY (submitted_by_id)
                REFERENCES `user` (id) ON DELETE CASCADE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE developer_app_application
                ADD CONSTRAINT FK_developer_app_reviewed_by FOREIGN KEY (reviewed_by_id)
                REFERENCES `user` (id) ON DELETE SET NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE developer_app_application
                ADD CONSTRAINT FK_developer_app_logo_image FOREIGN KEY (logo_image_id)
                REFERENCES image (id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE developer_app_application DROP FOREIGN KEY FK_developer_app_logo_image');
        $this->addSql('ALTER TABLE developer_app_application DROP FOREIGN KEY FK_developer_app_reviewed_by');
        $this->addSql('ALTER TABLE developer_app_application DROP FOREIGN KEY FK_developer_app_submitted_by');
        $this->addSql('DROP TABLE developer_app_application');
    }
}
