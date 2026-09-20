<?php declare(strict_types=1);

namespace PluginGlossaryMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Glossary trainer: per-member card scheduling and daily roll-up tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE plg_glossary_trainer_card (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, direction VARCHAR(20) NOT NULL, state VARCHAR(12) NOT NULL, due_at DATETIME DEFAULT NULL, interval_days INT NOT NULL, ease_permille INT NOT NULL, repetitions INT NOT NULL, lapses INT NOT NULL, times_seen INT NOT NULL, times_correct INT NOT NULL, marked TINYINT NOT NULL, suspended TINYINT NOT NULL, last_reviewed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, glossary_id INT NOT NULL, INDEX idx_glossary_card_due (user_id, state, due_at), UNIQUE INDEX uniq_glossary_card_user_entry_direction (user_id, glossary_id, direction), INDEX IDX_1681E7346ABB587D (glossary_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'CREATE TABLE plg_glossary_trainer_day (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, day DATE NOT NULL, reviewed INT NOT NULL, correct INT NOT NULL, new_started INT NOT NULL, UNIQUE INDEX uniq_glossary_day_user_day (user_id, day), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'ALTER TABLE plg_glossary_trainer_card ADD CONSTRAINT FK_1681E7346ABB587D FOREIGN KEY (glossary_id) REFERENCES plg_glossary_glossary (id) ON DELETE CASCADE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_glossary_trainer_card DROP FOREIGN KEY FK_1681E7346ABB587D');
        $this->addSql('DROP TABLE plg_glossary_trainer_card');
        $this->addSql('DROP TABLE plg_glossary_trainer_day');
    }
}
