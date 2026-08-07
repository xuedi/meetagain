<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807121437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rsvp_guest table storing per-attendee guest counts for event RSVPs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE rsvp_guest (guests SMALLINT NOT NULL, event_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_C9025DF571F7E88B (event_id), INDEX IDX_C9025DF5A76ED395 (user_id), PRIMARY KEY (event_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE rsvp_guest ADD CONSTRAINT FK_C9025DF571F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rsvp_guest ADD CONSTRAINT FK_C9025DF5A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rsvp_guest DROP FOREIGN KEY FK_C9025DF571F7E88B');
        $this->addSql('ALTER TABLE rsvp_guest DROP FOREIGN KEY FK_C9025DF5A76ED395');
        $this->addSql('DROP TABLE rsvp_guest');
    }
}
