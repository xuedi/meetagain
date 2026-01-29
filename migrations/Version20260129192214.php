<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Drop multisite permission tables - simplifying to role-based system.
 */
final class Version20260129192214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop multisite_permission and multisite_group_role_permission tables (simplifying to direct role-based checks)';
    }

    public function up(Schema $schema): void
    {
        // Drop foreign key constraint only if table exists
        // Use IF EXISTS pattern to handle tables that may not have been created yet
        $this->addSql('ALTER TABLE IF EXISTS multisite_group_role_permission DROP FOREIGN KEY IF EXISTS FK_2604F168FED90CCA');

        // Drop junction table if it exists
        $this->addSql('DROP TABLE IF EXISTS multisite_group_role_permission');

        // Drop permission table if it exists
        $this->addSql('DROP TABLE IF EXISTS multisite_permission');
    }

    public function down(Schema $schema): void
    {
        throw new RuntimeException('Cannot revert permission system simplification - this would recreate the complex database-driven permission system that was intentionally removed.');
    }
}
