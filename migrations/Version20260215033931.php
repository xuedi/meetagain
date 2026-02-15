<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260215033931 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CmsTitle and CmsLinkName translation entities for multi-language page titles and menu link names';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cms_link_name (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(2) NOT NULL, name VARCHAR(255) NOT NULL, cms_id INT NOT NULL, INDEX IDX_2C028CC2BE8A7CFB (cms_id), UNIQUE INDEX UNIQ_2C028CC2D4DB71B5BE8A7CFB (language, cms_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cms_title (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(2) NOT NULL, title VARCHAR(255) NOT NULL, cms_id INT NOT NULL, INDEX IDX_545E347BE8A7CFB (cms_id), UNIQUE INDEX UNIQ_545E347D4DB71B5BE8A7CFB (language, cms_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE cms_link_name ADD CONSTRAINT FK_2C028CC2BE8A7CFB FOREIGN KEY (cms_id) REFERENCES cms (id)');
        $this->addSql('ALTER TABLE cms_title ADD CONSTRAINT FK_545E347BE8A7CFB FOREIGN KEY (cms_id) REFERENCES cms (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cms_link_name DROP FOREIGN KEY FK_2C028CC2BE8A7CFB');
        $this->addSql('ALTER TABLE cms_title DROP FOREIGN KEY FK_545E347BE8A7CFB');
        $this->addSql('DROP TABLE cms_link_name');
        $this->addSql('DROP TABLE cms_title');
    }
}
