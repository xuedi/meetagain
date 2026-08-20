<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820013000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Support requests become link-addressed threads: drops the one-shot support_request table and creates the thread model (audience, requester, token, email verification, admin invitation) plus support_message. Existing support requests are discarded, not migrated.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS support_message');
        $this->addSql('DROP TABLE IF EXISTS support_request');

        $this->addSql('CREATE TABLE support_request (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) DEFAULT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, status VARCHAR(10) NOT NULL, audience VARCHAR(10) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, channel VARCHAR(10) NOT NULL, token VARCHAR(64) DEFAULT NULL, resolved_at DATETIME DEFAULT NULL, last_activity_at DATETIME DEFAULT NULL, email_verified_at DATETIME DEFAULT NULL, email_verify_token VARCHAR(64) DEFAULT NULL, email_verify_expires_at DATETIME DEFAULT NULL, invited_admins_at DATETIME DEFAULT NULL, requester_id INT DEFAULT NULL, responded_by_id INT DEFAULT NULL, invited_admins_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_86A287635F37A13B (token), UNIQUE INDEX UNIQ_86A28763CDF78CC8 (email_verify_token), INDEX IDX_86A28763ED442CF4 (requester_id), INDEX IDX_86A28763296135A7 (responded_by_id), INDEX IDX_86A287633D617FB6 (invited_admins_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE support_message (id INT AUTO_INCREMENT NOT NULL, author VARCHAR(10) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, support_request_id INT NOT NULL, author_user_id INT DEFAULT NULL, INDEX IDX_B88388360CA7C87 (support_request_id), INDEX IDX_B883883E2544CD6 (author_user_id), INDEX idx_support_message_thread (support_request_id, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE support_request ADD CONSTRAINT FK_86A28763ED442CF4 FOREIGN KEY (requester_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE support_request ADD CONSTRAINT FK_86A28763296135A7 FOREIGN KEY (responded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE support_request ADD CONSTRAINT FK_86A287633D617FB6 FOREIGN KEY (invited_admins_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE support_message ADD CONSTRAINT FK_B88388360CA7C87 FOREIGN KEY (support_request_id) REFERENCES support_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE support_message ADD CONSTRAINT FK_B883883E2544CD6 FOREIGN KEY (author_user_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE support_message');
        $this->addSql('DROP TABLE support_request');

        $this->addSql("CREATE TABLE support_request (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, status VARCHAR(10) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, contact_type VARCHAR(20) NOT NULL, responded_by_id INT DEFAULT NULL, response LONGTEXT DEFAULT NULL, reply_channel VARCHAR(10) DEFAULT NULL, INDEX IDX_SUPPORT_REQUEST_RESPONDED_BY (responded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql('ALTER TABLE support_request ADD CONSTRAINT FK_SUPPORT_REQUEST_RESPONDED_BY FOREIGN KEY (responded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }
}
