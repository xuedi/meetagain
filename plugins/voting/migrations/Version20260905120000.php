<?php declare(strict_types=1);

namespace PluginVotingMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A poll may have no event, so a ballot can exist without one.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_voting_poll CHANGE event_id event_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM plg_voting_poll WHERE event_id IS NULL');
        $this->addSql('ALTER TABLE plg_voting_poll CHANGE event_id event_id INT NOT NULL');
    }
}
