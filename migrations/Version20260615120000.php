<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track support reply ownership, stored response text and reply channel on support_request';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE support_request
                ADD responded_by_id INT DEFAULT NULL,
                ADD response LONGTEXT DEFAULT NULL,
                ADD reply_channel VARCHAR(10) DEFAULT NULL,
                ADD CONSTRAINT FK_SUPPORT_REQUEST_RESPONDED_BY
                    FOREIGN KEY (responded_by_id) REFERENCES user (id) ON DELETE SET NULL
        SQL);
        $this->addSql('CREATE INDEX IDX_SUPPORT_REQUEST_RESPONDED_BY ON support_request (responded_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE support_request DROP FOREIGN KEY FK_SUPPORT_REQUEST_RESPONDED_BY');
        $this->addSql('DROP INDEX IDX_SUPPORT_REQUEST_RESPONDED_BY ON support_request');
        $this->addSql('ALTER TABLE support_request DROP responded_by_id, DROP response, DROP reply_channel');
    }
}
