<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Downgrade all ORGANIZER users to USER (group roles drive effective permissions going forward)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE user SET role = 'USER' WHERE role = 'ORGANIZER'");
    }

    public function down(Schema $schema): void
    {
        // No meaningful rollback — group role assignments remain intact
    }
}
