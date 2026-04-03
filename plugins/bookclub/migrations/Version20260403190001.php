<?php declare(strict_types=1);

namespace PluginBookclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403190001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legacy datetime_immutable COMMENT annotations and align index names to Doctrine standard for bookclub tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE book_note CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE book_note RENAME INDEX idx_c4de433616a2b381 TO IDX_D620B11C16A2B381');
        $this->addSql('ALTER TABLE book_poll CHANGE created_at created_at DATETIME NOT NULL, CHANGE start_date start_date DATETIME DEFAULT NULL, CHANGE end_date end_date DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE book_poll_vote CHANGE voted_at voted_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE book_poll_vote RENAME INDEX idx_7b6d4b6f3c947c0f TO IDX_FFF898F83C947C0F');
        $this->addSql('ALTER TABLE book_poll_vote RENAME INDEX idx_7b6d4b6fa41bb822 TO IDX_FFF898F8A41BB822');
        $this->addSql('ALTER TABLE book_selection CHANGE selected_at selected_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE book_selection RENAME INDEX idx_5a28f72b16a2b381 TO IDX_840B1A9116A2B381');
        $this->addSql('ALTER TABLE book_selection RENAME INDEX idx_5a28f72b71f7e88b TO IDX_840B1A9171F7E88B');
        $this->addSql('ALTER TABLE book_suggestion CHANGE suggested_at suggested_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE book_suggestion RENAME INDEX idx_9ce5a94116a2b381 TO IDX_422DB9A816A2B381');
        $this->addSql('ALTER TABLE book_suggestion RENAME INDEX idx_9ce5a9413c947c0f TO IDX_422DB9A83C947C0F');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE book_note CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE book_note RENAME INDEX idx_d620b11c16a2b381 TO IDX_C4DE433616A2B381');
        $this->addSql('ALTER TABLE book_poll CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE start_date start_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE end_date end_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE book_poll_vote CHANGE voted_at voted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE book_poll_vote RENAME INDEX idx_fff898f83c947c0f TO IDX_7B6D4B6F3C947C0F');
        $this->addSql('ALTER TABLE book_poll_vote RENAME INDEX idx_fff898f8a41bb822 TO IDX_7B6D4B6FA41BB822');
        $this->addSql('ALTER TABLE book_selection CHANGE selected_at selected_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE book_selection RENAME INDEX idx_840b1a9116a2b381 TO IDX_5A28F72B16A2B381');
        $this->addSql('ALTER TABLE book_selection RENAME INDEX idx_840b1a9171f7e88b TO IDX_5A28F72B71F7E88B');
        $this->addSql('ALTER TABLE book_suggestion CHANGE suggested_at suggested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE book_suggestion RENAME INDEX idx_422db9a816a2b381 TO IDX_9CE5A94116A2B381');
        $this->addSql('ALTER TABLE book_suggestion RENAME INDEX idx_422db9a83c947c0f TO IDX_9CE5A9413C947C0F');
    }
}
