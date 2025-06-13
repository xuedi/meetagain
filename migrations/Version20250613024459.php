<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250613024459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE cms_block ADD image_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cms_block ADD CONSTRAINT FK_AD680C0E3DA5256D FOREIGN KEY (image_id) REFERENCES image (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_AD680C0E3DA5256D ON cms_block (image_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE cms_block DROP FOREIGN KEY FK_AD680C0E3DA5256D
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_AD680C0E3DA5256D ON cms_block
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cms_block DROP image_id
        SQL);
    }
}
