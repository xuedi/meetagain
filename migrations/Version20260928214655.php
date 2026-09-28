<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928214655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index activity.created_at and (user_id, created_at) so the admin activity log sorts, pages and counts without a full scan';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_AC74095A8B8E8428 ON activity (created_at)');
        $this->addSql('CREATE INDEX IDX_AC74095AA76ED3958B8E8428 ON activity (user_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_AC74095A8B8E8428 ON activity');
        $this->addSql('DROP INDEX IDX_AC74095AA76ED3958B8E8428 ON activity');
    }
}
