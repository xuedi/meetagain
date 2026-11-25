<?php declare(strict_types=1);

namespace PluginFediverseMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125135425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fediverse publications of events, and the events waiting to be published';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE plg_fediverse_pending_event (event_id INT NOT NULL, queued_at DATETIME NOT NULL, PRIMARY KEY (event_id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE plg_fediverse_publication (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, event_starts_at DATETIME NOT NULL, document LONGTEXT NOT NULL, hash VARCHAR(64) NOT NULL, announced TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, account_id INT NOT NULL, INDEX idx_fediverse_publication_event (event_id), INDEX idx_fediverse_publication_start (event_starts_at), INDEX IDX_3A1D02489B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'ALTER TABLE plg_fediverse_publication ADD CONSTRAINT FK_3A1D02489B6B5FBA FOREIGN KEY (account_id) REFERENCES plg_fediverse_account (id) ON DELETE CASCADE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_fediverse_publication DROP FOREIGN KEY FK_3A1D02489B6B5FBA');
        $this->addSql('DROP TABLE plg_fediverse_pending_event');
        $this->addSql('DROP TABLE plg_fediverse_publication');
    }
}
