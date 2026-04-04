<?php declare(strict_types=1);

namespace PluginDinnerclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404200002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move phonetic to dish, add pronunciation_system FK, remove origin_lang and dish_translation.phonetic';
    }

    public function up(Schema $schema): void
    {
        // 1. Add new columns
        $this->addSql('ALTER TABLE dish ADD phonetic VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE dish ADD pronunciation_system_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE dish ADD CONSTRAINT FK_dish_pronunciation_system
            FOREIGN KEY (pronunciation_system_id) REFERENCES pronunciation_system (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_dish_pronunciation_system ON dish (pronunciation_system_id)');

        // 2. Migrate phonetic: copy from origin-language translation (best-effort)
        $this->addSql('UPDATE dish d
            INNER JOIN dish_translation dt ON dt.dish_id = d.id
                AND dt.language = d.origin_lang
                AND dt.phonetic IS NOT NULL
                AND dt.phonetic != \'\'
            SET d.phonetic = dt.phonetic');

        // 3. Migrate pronunciation_system: map known origin_lang codes (best-effort)
        $this->addSql("UPDATE dish SET pronunciation_system_id =
            (SELECT id FROM pronunciation_system WHERE name = 'Pinyin' LIMIT 1)
            WHERE origin_lang = 'cn' AND phonetic IS NOT NULL");

        $this->addSql("UPDATE dish SET pronunciation_system_id =
            (SELECT id FROM pronunciation_system WHERE name = 'Rōmaji' LIMIT 1)
            WHERE origin_lang = 'ja' AND phonetic IS NOT NULL");

        $this->addSql("UPDATE dish SET pronunciation_system_id =
            (SELECT id FROM pronunciation_system WHERE name = 'Romanisation' LIMIT 1)
            WHERE origin_lang = 'ar' AND phonetic IS NOT NULL");

        $this->addSql("UPDATE dish SET pronunciation_system_id =
            (SELECT id FROM pronunciation_system WHERE name = 'Revised Romanisation' LIMIT 1)
            WHERE origin_lang = 'ko' AND phonetic IS NOT NULL");

        $this->addSql("UPDATE dish SET pronunciation_system_id =
            (SELECT id FROM pronunciation_system WHERE name = 'RTGS' LIMIT 1)
            WHERE origin_lang = 'th' AND phonetic IS NOT NULL");

        $this->addSql("UPDATE dish SET pronunciation_system_id =
            (SELECT id FROM pronunciation_system WHERE name = 'IAST' LIMIT 1)
            WHERE origin_lang = 'hi' AND phonetic IS NOT NULL");

        $this->addSql("UPDATE dish SET pronunciation_system_id =
            (SELECT id FROM pronunciation_system WHERE name = 'Greeklish' LIMIT 1)
            WHERE origin_lang = 'el' AND phonetic IS NOT NULL");

        // 4. Drop old columns
        $this->addSql('ALTER TABLE dish DROP COLUMN origin_lang');
        $this->addSql('ALTER TABLE dish_translation DROP COLUMN phonetic');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dish_translation ADD phonetic VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE dish ADD origin_lang VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE dish DROP FOREIGN KEY FK_dish_pronunciation_system');
        $this->addSql('DROP INDEX IDX_dish_pronunciation_system ON dish');
        $this->addSql('ALTER TABLE dish DROP COLUMN pronunciation_system_id');
        $this->addSql('ALTER TABLE dish DROP COLUMN phonetic');
    }
}
