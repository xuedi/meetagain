<?php

declare(strict_types=1);

namespace PluginBookclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260103100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bookclub plugin tables';
    }

    public function up(Schema $schema): void
    {
        // Book table
        $this->addSql('CREATE TABLE book (
            id INT AUTO_INCREMENT NOT NULL,
            isbn VARCHAR(17) NOT NULL,
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255) DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            page_count INT DEFAULT NULL,
            published_year INT DEFAULT NULL,
            cover_image_id INT DEFAULT NULL,
            approved TINYINT(1) NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_CBE5A331CC1CF4E6 (isbn),
            INDEX IDX_CBE5A331E5A0E336 (cover_image_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE book ADD CONSTRAINT FK_CBE5A331E5A0E336 FOREIGN KEY (cover_image_id) REFERENCES image (id)');

        // BookPoll table (with event_id from the start)
        $this->addSql('CREATE TABLE book_poll (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            start_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            end_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            status INT NOT NULL,
            event_id INT DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        // BookSuggestion table
        $this->addSql('CREATE TABLE book_suggestion (
            id INT AUTO_INCREMENT NOT NULL,
            book_id INT NOT NULL,
            poll_id INT DEFAULT NULL,
            suggested_by INT NOT NULL,
            suggested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            resubmit_count INT NOT NULL,
            status INT NOT NULL,
            INDEX IDX_9CE5A94116A2B381 (book_id),
            INDEX IDX_9CE5A9413C947C0F (poll_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE book_suggestion ADD CONSTRAINT FK_9CE5A94116A2B381 FOREIGN KEY (book_id) REFERENCES book (id)');
        $this->addSql('ALTER TABLE book_suggestion ADD CONSTRAINT FK_9CE5A9413C947C0F FOREIGN KEY (poll_id) REFERENCES book_poll (id)');

        // BookPollVote table
        $this->addSql('CREATE TABLE book_poll_vote (
            id INT AUTO_INCREMENT NOT NULL,
            poll_id INT NOT NULL,
            suggestion_id INT NOT NULL,
            user_id INT NOT NULL,
            voted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_7B6D4B6F3C947C0F (poll_id),
            INDEX IDX_7B6D4B6FA41BB822 (suggestion_id),
            UNIQUE INDEX unique_vote (poll_id, user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE book_poll_vote ADD CONSTRAINT FK_7B6D4B6F3C947C0F FOREIGN KEY (poll_id) REFERENCES book_poll (id)');
        $this->addSql('ALTER TABLE book_poll_vote ADD CONSTRAINT FK_7B6D4B6FA41BB822 FOREIGN KEY (suggestion_id) REFERENCES book_suggestion (id)');

        // BookSelection table
        $this->addSql('CREATE TABLE book_selection (
            id INT AUTO_INCREMENT NOT NULL,
            book_id INT NOT NULL,
            event_id INT NOT NULL,
            selected_by INT NOT NULL,
            selected_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_5A28F72B16A2B381 (book_id),
            INDEX IDX_5A28F72B71F7E88B (event_id),
            UNIQUE INDEX unique_event_book (event_id, book_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE book_selection ADD CONSTRAINT FK_5A28F72B16A2B381 FOREIGN KEY (book_id) REFERENCES book (id)');
        $this->addSql('ALTER TABLE book_selection ADD CONSTRAINT FK_5A28F72B71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');

        // BookNote table
        $this->addSql('CREATE TABLE book_note (
            id INT AUTO_INCREMENT NOT NULL,
            book_id INT NOT NULL,
            user_id INT NOT NULL,
            content LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_C4DE433616A2B381 (book_id),
            UNIQUE INDEX unique_user_book (user_id, book_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE book_note ADD CONSTRAINT FK_C4DE433616A2B381 FOREIGN KEY (book_id) REFERENCES book (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book_note DROP FOREIGN KEY FK_C4DE433616A2B381');
        $this->addSql('ALTER TABLE book_selection DROP FOREIGN KEY FK_5A28F72B16A2B381');
        $this->addSql('ALTER TABLE book_selection DROP FOREIGN KEY FK_5A28F72B71F7E88B');
        $this->addSql('ALTER TABLE book_poll_vote DROP FOREIGN KEY FK_7B6D4B6F3C947C0F');
        $this->addSql('ALTER TABLE book_poll_vote DROP FOREIGN KEY FK_7B6D4B6FA41BB822');
        $this->addSql('ALTER TABLE book_suggestion DROP FOREIGN KEY FK_9CE5A94116A2B381');
        $this->addSql('ALTER TABLE book_suggestion DROP FOREIGN KEY FK_9CE5A9413C947C0F');
        $this->addSql('ALTER TABLE book DROP FOREIGN KEY FK_CBE5A331E5A0E336');

        $this->addSql('DROP TABLE book_note');
        $this->addSql('DROP TABLE book_selection');
        $this->addSql('DROP TABLE book_poll_vote');
        $this->addSql('DROP TABLE book_suggestion');
        $this->addSql('DROP TABLE book_poll');
        $this->addSql('DROP TABLE book');
    }
}
