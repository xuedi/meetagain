<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove DB-backed theme color rows — colors are now file-backed via assets/styles/_config.scss';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM config WHERE name IN (
            'color_primary', 'color_link', 'color_info', 'color_success',
            'color_warning', 'color_danger', 'color_text_grey', 'color_text_grey_light'
        )");
    }

    public function down(Schema $schema): void
    {
        // No-op: defaults are now file-backed, not DB-backed.
    }
}
