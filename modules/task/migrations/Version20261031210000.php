<?php declare(strict_types=1);

namespace ModuleTaskMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Task module - the cursor a manual action continues from';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_task_action_request ADD step_cursor VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_task_action_request DROP step_cursor');
    }
}
