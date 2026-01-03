<?php

declare(strict_types=1);

namespace PluginBookclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260103115716 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create book club plugin tables: book, book_note, book_poll, book_poll_vote, book_selection, book_suggestion';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE book (id INT AUTO_INCREMENT NOT NULL, isbn VARCHAR(17) NOT NULL, title VARCHAR(255) NOT NULL, author VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, page_count INT DEFAULT NULL, published_year INT DEFAULT NULL, approved TINYINT NOT NULL, created_by INT NOT NULL, created_at DATETIME NOT NULL, cover_image_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_CBE5A331CC1CF4E6 (isbn), INDEX IDX_CBE5A331E5A0E336 (cover_image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE book_note (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, book_id INT NOT NULL, INDEX IDX_D620B11C16A2B381 (book_id), UNIQUE INDEX unique_user_book (user_id, book_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE book_poll (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, created_by INT NOT NULL, created_at DATETIME NOT NULL, start_date DATETIME DEFAULT NULL, end_date DATETIME DEFAULT NULL, status INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE book_poll_vote (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, voted_at DATETIME NOT NULL, poll_id INT NOT NULL, suggestion_id INT NOT NULL, INDEX IDX_FFF898F83C947C0F (poll_id), INDEX IDX_FFF898F8A41BB822 (suggestion_id), UNIQUE INDEX unique_vote (poll_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE book_selection (id INT AUTO_INCREMENT NOT NULL, selected_by INT NOT NULL, selected_at DATETIME NOT NULL, book_id INT NOT NULL, event_id INT NOT NULL, INDEX IDX_840B1A9116A2B381 (book_id), INDEX IDX_840B1A9171F7E88B (event_id), UNIQUE INDEX unique_event_book (event_id, book_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE book_suggestion (id INT AUTO_INCREMENT NOT NULL, suggested_by INT NOT NULL, suggested_at DATETIME NOT NULL, resubmit_count INT NOT NULL, status INT NOT NULL, book_id INT NOT NULL, poll_id INT DEFAULT NULL, INDEX IDX_422DB9A816A2B381 (book_id), INDEX IDX_422DB9A83C947C0F (poll_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE book ADD CONSTRAINT FK_CBE5A331E5A0E336 FOREIGN KEY (cover_image_id) REFERENCES image (id)');
        $this->addSql('ALTER TABLE book_note ADD CONSTRAINT FK_D620B11C16A2B381 FOREIGN KEY (book_id) REFERENCES book (id)');
        $this->addSql('ALTER TABLE book_poll_vote ADD CONSTRAINT FK_FFF898F83C947C0F FOREIGN KEY (poll_id) REFERENCES book_poll (id)');
        $this->addSql('ALTER TABLE book_poll_vote ADD CONSTRAINT FK_FFF898F8A41BB822 FOREIGN KEY (suggestion_id) REFERENCES book_suggestion (id)');
        $this->addSql('ALTER TABLE book_selection ADD CONSTRAINT FK_840B1A9116A2B381 FOREIGN KEY (book_id) REFERENCES book (id)');
        $this->addSql('ALTER TABLE book_selection ADD CONSTRAINT FK_840B1A9171F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE book_suggestion ADD CONSTRAINT FK_422DB9A816A2B381 FOREIGN KEY (book_id) REFERENCES book (id)');
        $this->addSql('ALTER TABLE book_suggestion ADD CONSTRAINT FK_422DB9A83C947C0F FOREIGN KEY (poll_id) REFERENCES book_poll (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE book DROP FOREIGN KEY FK_CBE5A331E5A0E336');
        $this->addSql('ALTER TABLE book_note DROP FOREIGN KEY FK_D620B11C16A2B381');
        $this->addSql('ALTER TABLE book_poll_vote DROP FOREIGN KEY FK_FFF898F83C947C0F');
        $this->addSql('ALTER TABLE book_poll_vote DROP FOREIGN KEY FK_FFF898F8A41BB822');
        $this->addSql('ALTER TABLE book_selection DROP FOREIGN KEY FK_840B1A9116A2B381');
        $this->addSql('ALTER TABLE book_selection DROP FOREIGN KEY FK_840B1A9171F7E88B');
        $this->addSql('ALTER TABLE book_suggestion DROP FOREIGN KEY FK_422DB9A816A2B381');
        $this->addSql('ALTER TABLE book_suggestion DROP FOREIGN KEY FK_422DB9A83C947C0F');
        $this->addSql('DROP TABLE book');
        $this->addSql('DROP TABLE book_note');
        $this->addSql('DROP TABLE book_poll');
        $this->addSql('DROP TABLE book_poll_vote');
        $this->addSql('DROP TABLE book_selection');
        $this->addSql('DROP TABLE book_suggestion');
    }
}
