<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224010443 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace legacy JSON roles array with single enum role column';
    }

    public function up(Schema $schema): void
    {
        // Step 1: add the new column (nullable so we can populate it)
        $this->addSql('ALTER TABLE `user` ADD role VARCHAR(255) NOT NULL DEFAULT \'USER\', CHANGE regcode_expires_at regcode_expires_at DATETIME DEFAULT NULL');

        // Step 2: populate from the existing single-item JSON roles array
        $this->addSql("UPDATE `user` SET role = CASE JSON_UNQUOTE(JSON_EXTRACT(roles, '$[0]'))
            WHEN 'ROLE_ADMIN'     THEN 'ADMIN'
            WHEN 'ROLE_FOUNDER'   THEN 'FOUNDER'
            WHEN 'ROLE_ORGANIZER' THEN 'ORGANIZER'
            WHEN 'ROLE_SYSTEM'    THEN 'SYSTEM'
            ELSE 'USER'
        END");

        // Step 3: drop the old JSON column
        $this->addSql('ALTER TABLE `user` DROP roles');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD roles JSON NOT NULL DEFAULT (JSON_ARRAY(CONCAT(\'ROLE_\', role))), DROP role, CHANGE regcode_expires_at regcode_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
