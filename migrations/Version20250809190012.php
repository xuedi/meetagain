<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250809190012 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates menu and menu_translation tables with foreign key relationships to cms, event tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE menu (id INT AUTO_INCREMENT NOT NULL, location INT NOT NULL, priority DOUBLE PRECISION NOT NULL, visibility INT NOT NULL, type INT NOT NULL, slug VARCHAR(255) DEFAULT NULL, route VARCHAR(255) DEFAULT NULL, cms_id INT DEFAULT NULL, event_id INT DEFAULT NULL, INDEX IDX_7D053A93BE8A7CFB (cms_id), INDEX IDX_7D053A9371F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE menu_translation (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(2) NOT NULL, name VARCHAR(255) NOT NULL, menu_id INT NOT NULL, INDEX IDX_DC955B23CCD7E912 (menu_id), UNIQUE INDEX UNIQ_DC955B23D4DB71B5CCD7E912 (language, menu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE menu ADD CONSTRAINT FK_7D053A93BE8A7CFB FOREIGN KEY (cms_id) REFERENCES cms (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE menu ADD CONSTRAINT FK_7D053A9371F7E88B FOREIGN KEY (event_id) REFERENCES event (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE menu_translation ADD CONSTRAINT FK_DC955B23CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE menu DROP FOREIGN KEY FK_7D053A93BE8A7CFB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE menu DROP FOREIGN KEY FK_7D053A9371F7E88B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE menu_translation DROP FOREIGN KEY FK_DC955B23CCD7E912
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE menu
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE menu_translation
        SQL);
    }
}
