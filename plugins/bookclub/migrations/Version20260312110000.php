<?php

declare(strict_types=1);

namespace PluginBookclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove draft status from book_poll - activate any remaining draft polls';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE book_poll SET status = 1, start_date = NOW() WHERE status = 0');
    }

    public function down(Schema $schema): void
    {
        // Cannot restore original draft status
    }
}
