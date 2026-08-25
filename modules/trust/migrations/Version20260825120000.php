<?php

declare(strict_types=1);

namespace ModuleTrustMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trust module 1.0 - member-to-member vouches and per-context configuration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mod_trust_context_config (
            id INT AUTO_INCREMENT NOT NULL,
            context VARCHAR(191) NOT NULL,
            payload JSON NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_29D8871DE25D857E (context),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE mod_trust_grant (
            id INT AUTO_INCREMENT NOT NULL,
            context VARCHAR(191) NOT NULL,
            level VARCHAR(16) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            from_user_id INT NOT NULL,
            to_user_id INT NOT NULL,
            INDEX IDX_FD7BC1142130303A (from_user_id),
            INDEX IDX_FD7BC11429F6EE60 (to_user_id),
            INDEX idx_trust_grant_context (context),
            UNIQUE INDEX uniq_trust_grant_edge (context, from_user_id, to_user_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE mod_trust_grant ADD CONSTRAINT FK_FD7BC1142130303A FOREIGN KEY (from_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mod_trust_grant ADD CONSTRAINT FK_FD7BC11429F6EE60 FOREIGN KEY (to_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_trust_grant DROP FOREIGN KEY FK_FD7BC1142130303A');
        $this->addSql('ALTER TABLE mod_trust_grant DROP FOREIGN KEY FK_FD7BC11429F6EE60');
        $this->addSql('DROP TABLE mod_trust_grant');
        $this->addSql('DROP TABLE mod_trust_context_config');
    }
}
