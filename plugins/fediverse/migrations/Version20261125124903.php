<?php declare(strict_types=1);

namespace PluginFediverseMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125124903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fediverse accounts: one per scope, with its key pair and its lock and retirement times';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE plg_fediverse_account (id INT AUTO_INCREMENT NOT NULL, scope_id VARCHAR(64) DEFAULT NULL, username VARCHAR(30) NOT NULL, public_key LONGTEXT NOT NULL, encrypted_private_key LONGTEXT NOT NULL, created_at DATETIME NOT NULL, locked_at DATETIME DEFAULT NULL, retired_at DATETIME DEFAULT NULL, INDEX idx_fediverse_account_scope (scope_id), UNIQUE INDEX uniq_fediverse_account_username (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE plg_fediverse_account');
    }
}
