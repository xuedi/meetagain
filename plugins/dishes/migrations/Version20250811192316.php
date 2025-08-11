<?php

declare(strict_types=1);

namespace PluginDishesMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250811192316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'added translations';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE dish_translation (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(2) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, dish_id INT NOT NULL, INDEX IDX_6678768A148EB0CB (dish_id), UNIQUE INDEX UNIQ_6678768AD4DB71B5148EB0CB (language, dish_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE dish_translation ADD CONSTRAINT FK_6678768A148EB0CB FOREIGN KEY (dish_id) REFERENCES dish (id)');
        $this->addSql('ALTER TABLE dish ADD created_at DATETIME NOT NULL, ADD created_by INT NOT NULL, ADD approved TINYINT(1) NOT NULL, DROP name');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dish_translation DROP FOREIGN KEY FK_6678768A148EB0CB');
        $this->addSql('DROP TABLE dish_translation');
        $this->addSql('ALTER TABLE dish ADD name VARCHAR(255) NOT NULL, DROP created_at, DROP created_by, DROP approved');
    }
}
