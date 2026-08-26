<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Item circulation - physical copies, the per-title waiting list, two-sided handovers and the append-only ledger.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE circulation_copy (
            id INT AUTO_INCREMENT NOT NULL,
            item_type VARCHAR(50) NOT NULL,
            item_id INT NOT NULL,
            context VARCHAR(191) NOT NULL,
            label VARCHAR(255) DEFAULT NULL,
            donated_at DATETIME NOT NULL,
            held_since DATETIME DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            finished_at DATETIME DEFAULT NULL,
            donated_by_id INT DEFAULT NULL,
            holder_id INT DEFAULT NULL,
            INDEX idx_circulation_copy_item (context, item_type, item_id),
            INDEX idx_circulation_copy_status (context, item_type, status),
            INDEX idx_circulation_copy_holder (holder_id),
            INDEX IDX_53123B6A7CDFEE88 (donated_by_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE circulation_request (
            id INT AUTO_INCREMENT NOT NULL,
            context VARCHAR(191) NOT NULL,
            item_type VARCHAR(50) NOT NULL,
            item_id INT NOT NULL,
            requested_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL,
            open_slot INT DEFAULT NULL,
            offered_at DATETIME DEFAULT NULL,
            user_id INT NOT NULL,
            offered_copy_id INT DEFAULT NULL,
            INDEX idx_circulation_request_queue (context, item_type, item_id, status),
            INDEX idx_circulation_request_user (user_id, status),
            INDEX IDX_7FE8BD08828DB255 (offered_copy_id),
            UNIQUE INDEX uniq_circulation_request_open (context, item_type, item_id, user_id, open_slot),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE circulation_handover (
            id INT AUTO_INCREMENT NOT NULL,
            opened_at DATETIME NOT NULL,
            from_confirmed_at DATETIME DEFAULT NULL,
            to_confirmed_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            copy_id INT NOT NULL,
            from_user_id INT DEFAULT NULL,
            to_user_id INT NOT NULL,
            request_id INT DEFAULT NULL,
            cancelled_by_id INT DEFAULT NULL,
            INDEX idx_circulation_handover_status (status, opened_at),
            INDEX idx_circulation_handover_copy (copy_id),
            INDEX IDX_EE3699012130303A (from_user_id),
            INDEX IDX_EE36990129F6EE60 (to_user_id),
            INDEX IDX_EE369901427EB8A5 (request_id),
            INDEX IDX_EE369901187B2D12 (cancelled_by_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE circulation_ledger (
            id INT AUTO_INCREMENT NOT NULL,
            occurred_at DATETIME NOT NULL,
            recorded_at DATETIME NOT NULL,
            entry_type VARCHAR(30) NOT NULL,
            context VARCHAR(191) NOT NULL,
            item_type VARCHAR(50) NOT NULL,
            item_id INT NOT NULL,
            copy_id INT DEFAULT NULL,
            from_user_id INT DEFAULT NULL,
            to_user_id INT DEFAULT NULL,
            actor_user_id INT DEFAULT NULL,
            payload JSON NOT NULL,
            INDEX idx_circulation_ledger_context (context, id),
            INDEX idx_circulation_ledger_item (context, item_type, item_id),
            INDEX idx_circulation_ledger_copy (copy_id),
            INDEX idx_circulation_ledger_type (context, entry_type),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE circulation_copy ADD CONSTRAINT FK_circulation_copy_donated_by FOREIGN KEY (donated_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE circulation_copy ADD CONSTRAINT FK_circulation_copy_holder FOREIGN KEY (holder_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE circulation_request ADD CONSTRAINT FK_circulation_request_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE circulation_request ADD CONSTRAINT FK_circulation_request_offered_copy FOREIGN KEY (offered_copy_id) REFERENCES circulation_copy (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE circulation_handover ADD CONSTRAINT FK_circulation_handover_copy FOREIGN KEY (copy_id) REFERENCES circulation_copy (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE circulation_handover ADD CONSTRAINT FK_circulation_handover_from_user FOREIGN KEY (from_user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE circulation_handover ADD CONSTRAINT FK_circulation_handover_to_user FOREIGN KEY (to_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE circulation_handover ADD CONSTRAINT FK_circulation_handover_request FOREIGN KEY (request_id) REFERENCES circulation_request (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE circulation_handover ADD CONSTRAINT FK_circulation_handover_cancelled_by FOREIGN KEY (cancelled_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE circulation_handover DROP FOREIGN KEY FK_circulation_handover_copy');
        $this->addSql('ALTER TABLE circulation_handover DROP FOREIGN KEY FK_circulation_handover_from_user');
        $this->addSql('ALTER TABLE circulation_handover DROP FOREIGN KEY FK_circulation_handover_to_user');
        $this->addSql('ALTER TABLE circulation_handover DROP FOREIGN KEY FK_circulation_handover_request');
        $this->addSql('ALTER TABLE circulation_handover DROP FOREIGN KEY FK_circulation_handover_cancelled_by');
        $this->addSql('ALTER TABLE circulation_request DROP FOREIGN KEY FK_circulation_request_user');
        $this->addSql('ALTER TABLE circulation_request DROP FOREIGN KEY FK_circulation_request_offered_copy');
        $this->addSql('ALTER TABLE circulation_copy DROP FOREIGN KEY FK_circulation_copy_donated_by');
        $this->addSql('ALTER TABLE circulation_copy DROP FOREIGN KEY FK_circulation_copy_holder');
        $this->addSql('DROP TABLE circulation_handover');
        $this->addSql('DROP TABLE circulation_request');
        $this->addSql('DROP TABLE circulation_ledger');
        $this->addSql('DROP TABLE circulation_copy');
    }
}
