<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'An event remembers when it told its attendees about the cancellation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD canceled_notice_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP canceled_notice_sent_at');
    }
}
