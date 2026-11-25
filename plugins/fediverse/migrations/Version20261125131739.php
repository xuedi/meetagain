<?php declare(strict_types=1);

namespace PluginFediverseMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125131739 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fediverse followers per account, and the queue of signed deliveries to remote inboxes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE plg_fediverse_delivery (id INT AUTO_INCREMENT NOT NULL, inbox VARCHAR(512) NOT NULL, activity LONGTEXT NOT NULL, attempts INT NOT NULL, due_at DATETIME NOT NULL, claim_token VARCHAR(32) DEFAULT NULL, claimed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, account_id INT NOT NULL, INDEX idx_fediverse_delivery_due (due_at), INDEX idx_fediverse_delivery_claim (claim_token), INDEX IDX_153EAC359B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE plg_fediverse_follower (id INT AUTO_INCREMENT NOT NULL, actor_id VARCHAR(512) NOT NULL, inbox VARCHAR(512) NOT NULL, shared_inbox VARCHAR(512) DEFAULT NULL, follow_id VARCHAR(512) NOT NULL, created_at DATETIME NOT NULL, account_id INT NOT NULL, INDEX idx_fediverse_follower_actor (actor_id), UNIQUE INDEX uniq_fediverse_follower_account_actor (account_id, actor_id), INDEX IDX_9B6949639B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'ALTER TABLE plg_fediverse_delivery ADD CONSTRAINT FK_153EAC359B6B5FBA FOREIGN KEY (account_id) REFERENCES plg_fediverse_account (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'ALTER TABLE plg_fediverse_follower ADD CONSTRAINT FK_9B6949639B6B5FBA FOREIGN KEY (account_id) REFERENCES plg_fediverse_account (id) ON DELETE CASCADE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_fediverse_delivery DROP FOREIGN KEY FK_153EAC359B6B5FBA');
        $this->addSql('ALTER TABLE plg_fediverse_follower DROP FOREIGN KEY FK_9B6949639B6B5FBA');
        $this->addSql('DROP TABLE plg_fediverse_delivery');
        $this->addSql('DROP TABLE plg_fediverse_follower');
    }
}
