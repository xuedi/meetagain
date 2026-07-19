<?php declare(strict_types=1);

namespace PluginGlossaryMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make the glossary shape configurable: pinyin (the optional secondary field) and category
 * (now a config category id, not a fixed enum) both become nullable. Existing rows keep
 * their pinyin string and their category int.
 */
final class Version20260712141000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make glossary.pinyin and glossary.category nullable for the configurable glossary shape';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE plg_glossary_glossary
                CHANGE pinyin pinyin VARCHAR(255) DEFAULT NULL,
                CHANGE category category INT DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE plg_glossary_glossary
                CHANGE pinyin pinyin VARCHAR(255) NOT NULL,
                CHANGE category category INT NOT NULL
        SQL);
    }
}
