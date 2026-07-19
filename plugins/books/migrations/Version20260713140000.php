<?php

declare(strict_types=1);

namespace PluginBooksMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Books 1.0 - book catalog with ISBN and cover image (rebuilt from bookclub, no production data)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plg_books_book (
            id INT AUTO_INCREMENT NOT NULL,
            cover_image_id INT DEFAULT NULL,
            isbn VARCHAR(17) NOT NULL,
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255) DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            page_count INT DEFAULT NULL,
            published_year INT DEFAULT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_13F76E2ACC1CF4E6 (isbn),
            INDEX IDX_13F76E2AE5A0E336 (cover_image_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE plg_books_book ADD CONSTRAINT FK_13F76E2AE5A0E336 FOREIGN KEY (cover_image_id) REFERENCES image (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_books_book DROP FOREIGN KEY FK_13F76E2AE5A0E336');
        $this->addSql('DROP TABLE plg_books_book');
    }
}
