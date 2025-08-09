<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726215811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add menu and translations';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE menu (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, location INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE menu_translation (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(2) NOT NULL, name VARCHAR(255) NOT NULL, menu_id INT NOT NULL, INDEX IDX_DC955B23CCD7E912 (menu_id), UNIQUE INDEX UNIQ_DC955B23D4DB71B5CCD7E912 (language, menu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE menu_translation ADD CONSTRAINT FK_DC955B23CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
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
