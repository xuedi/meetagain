<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Plugins are switched on and off at runtime: one row per plugin that was ever toggled';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE plugin_activation (plugin_key VARCHAR(64) NOT NULL, enabled TINYINT NOT NULL, changed_at DATETIME NOT NULL, PRIMARY KEY (plugin_key)) DEFAULT CHARACTER SET utf8mb4',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE plugin_activation');
    }
}
