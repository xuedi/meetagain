<?php declare(strict_types=1);

namespace PluginRankingMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260524120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ranking 1.0 - initial schema (ranking_config, ranking_rank_definition, ranking_member_rank, ranking_rank_history)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ranking_config (
            id INT AUTO_INCREMENT NOT NULL,
            group_id INT NOT NULL,
            archetype VARCHAR(20) NOT NULL,
            show_badge TINYINT NOT NULL,
            show_on_member_list TINYINT NOT NULL,
            show_leaderboard_nav TINYINT NOT NULL,
            UNIQUE INDEX unique_ranking_config_group (group_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE ranking_rank_definition (
            id INT AUTO_INCREMENT NOT NULL,
            label VARCHAR(100) NOT NULL,
            label_key VARCHAR(100) DEFAULT NULL,
            color_hex VARCHAR(7) DEFAULT NULL,
            position INT NOT NULL,
            config_id INT NOT NULL,
            INDEX IDX_4DCE929424DB0683 (config_id),
            INDEX idx_rank_definition_config_position (config_id, position),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE ranking_rank_definition ADD CONSTRAINT FK_4DCE929424DB0683 FOREIGN KEY (config_id) REFERENCES ranking_config (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE ranking_member_rank (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            group_id INT NOT NULL,
            numeric_value INT DEFAULT NULL,
            rank_definition_id INT DEFAULT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE INDEX unique_member_rank_user_group (user_id, group_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE ranking_rank_history (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            group_id INT NOT NULL,
            actor_user_id INT NOT NULL,
            reason VARCHAR(30) NOT NULL,
            old_value VARCHAR(255) DEFAULT NULL,
            new_value VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_rank_history_user_group_created (user_id, group_id, created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ranking_rank_history');
        $this->addSql('DROP TABLE ranking_member_rank');
        $this->addSql('ALTER TABLE ranking_rank_definition DROP FOREIGN KEY FK_4DCE929424DB0683');
        $this->addSql('DROP TABLE ranking_rank_definition');
        $this->addSql('DROP TABLE ranking_config');
    }
}
