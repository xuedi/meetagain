<?php

declare(strict_types=1);

namespace PluginBoardgamesMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Boardgames 1.0 - group game catalog, per-member shelves, bring pledges and bring requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plg_boardgames_game (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) DEFAULT NULL,
            year_published INT DEFAULT NULL,
            min_players INT DEFAULT NULL,
            max_players INT DEFAULT NULL,
            best_player_count INT DEFAULT NULL,
            min_playtime INT DEFAULT NULL,
            max_playtime INT DEFAULT NULL,
            min_age INT DEFAULT NULL,
            weight NUMERIC(3, 2) DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            external_source VARCHAR(10) NOT NULL,
            external_id VARCHAR(64) DEFAULT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL,
            box_image_id INT DEFAULT NULL,
            INDEX IDX_5801FE9C6C62E7C9 (box_image_id),
            UNIQUE INDEX uniq_boardgames_game_external (external_source, external_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE plg_boardgames_ownership (
            id INT AUTO_INCREMENT NOT NULL,
            copy_language VARCHAR(8) DEFAULT NULL,
            copy_condition VARCHAR(16) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            can_teach TINYINT NOT NULL,
            willing_to_bring TINYINT NOT NULL,
            is_public TINYINT NOT NULL,
            acquired_at DATE DEFAULT NULL,
            created_at DATETIME NOT NULL,
            user_id INT NOT NULL,
            game_id INT NOT NULL,
            INDEX IDX_AF4FE64DA76ED395 (user_id),
            INDEX IDX_AF4FE64DE48FD905 (game_id),
            UNIQUE INDEX uniq_boardgames_ownership_user_game (user_id, game_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE plg_boardgames_pledge (
            id INT AUTO_INCREMENT NOT NULL,
            status VARCHAR(16) NOT NULL,
            created_at DATETIME NOT NULL,
            event_id INT NOT NULL,
            game_id INT NOT NULL,
            user_id INT NOT NULL,
            INDEX IDX_F3798C0E71F7E88B (event_id),
            INDEX IDX_F3798C0EE48FD905 (game_id),
            INDEX IDX_F3798C0EA76ED395 (user_id),
            UNIQUE INDEX uniq_boardgames_pledge_event_game_user (event_id, game_id, user_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE plg_boardgames_bring_request (
            id INT AUTO_INCREMENT NOT NULL,
            status VARCHAR(16) NOT NULL,
            message LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            responded_at DATETIME DEFAULT NULL,
            event_id INT NOT NULL,
            game_id INT NOT NULL,
            requested_by_id INT NOT NULL,
            owner_user_id INT NOT NULL,
            INDEX IDX_5A8C7B5E71F7E88B (event_id),
            INDEX IDX_5A8C7B5EE48FD905 (game_id),
            INDEX IDX_5A8C7B5E4DA1E751 (requested_by_id),
            INDEX IDX_5A8C7B5E2B18554A (owner_user_id),
            UNIQUE INDEX uniq_boardgames_request_event_game_requester (event_id, game_id, requested_by_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE plg_boardgames_game ADD CONSTRAINT FK_5801FE9C6C62E7C9 FOREIGN KEY (box_image_id) REFERENCES image (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE plg_boardgames_ownership ADD CONSTRAINT FK_AF4FE64DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plg_boardgames_ownership ADD CONSTRAINT FK_AF4FE64DE48FD905 FOREIGN KEY (game_id) REFERENCES plg_boardgames_game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plg_boardgames_pledge ADD CONSTRAINT FK_F3798C0E71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plg_boardgames_pledge ADD CONSTRAINT FK_F3798C0EE48FD905 FOREIGN KEY (game_id) REFERENCES plg_boardgames_game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plg_boardgames_pledge ADD CONSTRAINT FK_F3798C0EA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plg_boardgames_bring_request ADD CONSTRAINT FK_5A8C7B5E71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plg_boardgames_bring_request ADD CONSTRAINT FK_5A8C7B5EE48FD905 FOREIGN KEY (game_id) REFERENCES plg_boardgames_game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plg_boardgames_bring_request ADD CONSTRAINT FK_5A8C7B5E4DA1E751 FOREIGN KEY (requested_by_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plg_boardgames_bring_request ADD CONSTRAINT FK_5A8C7B5E2B18554A FOREIGN KEY (owner_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_boardgames_bring_request DROP FOREIGN KEY FK_5A8C7B5E71F7E88B');
        $this->addSql('ALTER TABLE plg_boardgames_bring_request DROP FOREIGN KEY FK_5A8C7B5EE48FD905');
        $this->addSql('ALTER TABLE plg_boardgames_bring_request DROP FOREIGN KEY FK_5A8C7B5E4DA1E751');
        $this->addSql('ALTER TABLE plg_boardgames_bring_request DROP FOREIGN KEY FK_5A8C7B5E2B18554A');
        $this->addSql('ALTER TABLE plg_boardgames_pledge DROP FOREIGN KEY FK_F3798C0E71F7E88B');
        $this->addSql('ALTER TABLE plg_boardgames_pledge DROP FOREIGN KEY FK_F3798C0EE48FD905');
        $this->addSql('ALTER TABLE plg_boardgames_pledge DROP FOREIGN KEY FK_F3798C0EA76ED395');
        $this->addSql('ALTER TABLE plg_boardgames_ownership DROP FOREIGN KEY FK_AF4FE64DA76ED395');
        $this->addSql('ALTER TABLE plg_boardgames_ownership DROP FOREIGN KEY FK_AF4FE64DE48FD905');
        $this->addSql('ALTER TABLE plg_boardgames_game DROP FOREIGN KEY FK_5801FE9C6C62E7C9');
        $this->addSql('DROP TABLE plg_boardgames_bring_request');
        $this->addSql('DROP TABLE plg_boardgames_pledge');
        $this->addSql('DROP TABLE plg_boardgames_ownership');
        $this->addSql('DROP TABLE plg_boardgames_game');
    }
}
