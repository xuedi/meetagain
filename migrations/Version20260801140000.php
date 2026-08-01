<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make comments polymorphic: target_type + target_id instead of the event FK, nullable author';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment ADD target_type VARCHAR(32) DEFAULT NULL');
        $this->addSql("UPDATE comment SET target_type = 'event'");
        $this->addSql('ALTER TABLE comment MODIFY target_type VARCHAR(32) NOT NULL');

        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C71F7E88B');
        $this->addSql('DROP INDEX IDX_9474526C71F7E88B ON comment');
        $this->addSql('ALTER TABLE comment CHANGE event_id target_id INT NOT NULL');

        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395');
        $this->addSql('ALTER TABLE comment CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->addSql('CREATE INDEX idx_comment_target ON comment (target_type, target_id)');
        $this->addSql('CREATE INDEX idx_comment_target_created ON comment (target_type, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_comment_target_created ON comment');
        $this->addSql('DROP INDEX idx_comment_target ON comment');

        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395');
        $this->addSql('DELETE FROM comment WHERE user_id IS NULL');
        $this->addSql('ALTER TABLE comment CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');

        $this->addSql("DELETE FROM comment WHERE target_type <> 'event'");
        $this->addSql('ALTER TABLE comment CHANGE target_id event_id INT NOT NULL');
        $this->addSql('CREATE INDEX IDX_9474526C71F7E88B ON comment (event_id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE comment DROP target_type');
    }
}
