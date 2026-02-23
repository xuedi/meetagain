<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize user roles to single highest role — removes redundant multi-role arrays';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE `user` SET roles = JSON_ARRAY(\'ROLE_ADMIN\') WHERE JSON_CONTAINS(roles, \'\"ROLE_ADMIN\"\')');
        $this->addSql('UPDATE `user` SET roles = JSON_ARRAY(\'ROLE_FOUNDER\') WHERE JSON_CONTAINS(roles, \'\"ROLE_FOUNDER\"\') AND NOT JSON_CONTAINS(roles, \'\"ROLE_ADMIN\"\')');
        $this->addSql('UPDATE `user` SET roles = JSON_ARRAY(\'ROLE_ORGANIZER\') WHERE JSON_CONTAINS(roles, \'\"ROLE_ORGANIZER\"\') AND NOT JSON_CONTAINS(roles, \'\"ROLE_FOUNDER\"\') AND NOT JSON_CONTAINS(roles, \'\"ROLE_ADMIN\"\')');
        $this->addSql('UPDATE `user` SET roles = JSON_ARRAY(\'ROLE_USER\') WHERE JSON_LENGTH(roles) = 0 OR roles IS NULL OR (NOT JSON_CONTAINS(roles, \'\"ROLE_ORGANIZER\"\') AND NOT JSON_CONTAINS(roles, \'\"ROLE_FOUNDER\"\') AND NOT JSON_CONTAINS(roles, \'\"ROLE_ADMIN\"\'))');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Role normalization cannot be reversed — original multi-role arrays are not recoverable.');
    }
}
