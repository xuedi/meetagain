<?php declare(strict_types=1);

namespace PluginGlossaryMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move glossary explanations into per-locale definition rows and rename the field inside stored glossary reviews';
    }

    public function up(Schema $schema): void
    {
        $locale = $this->sourceLocale();

        $this->addSql(
            'CREATE TABLE plg_glossary_definition (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(2) NOT NULL, text LONGTEXT NOT NULL, glossary_id INT NOT NULL, UNIQUE INDEX uniq_glossary_definition_lang_entry (language, glossary_id), INDEX IDX_ECAC27B66ABB587D (glossary_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4',
        );
        $this->addSql(
            'ALTER TABLE plg_glossary_definition ADD CONSTRAINT FK_ECAC27B66ABB587D FOREIGN KEY (glossary_id) REFERENCES plg_glossary_glossary (id) ON DELETE CASCADE',
        );
        $this->addSql("INSERT INTO plg_glossary_definition (glossary_id, language, text) SELECT id, ?, explanation FROM plg_glossary_glossary WHERE TRIM(explanation) <> ''", [
            $locale,
        ]);
        $this->addSql('ALTER TABLE plg_glossary_glossary DROP explanation');
        $this->renameStoredField('explanation', 'definition_' . $locale);
    }

    public function down(Schema $schema): void
    {
        $locale = $this->sourceLocale();

        $this->addSql('ALTER TABLE plg_glossary_glossary ADD explanation LONGTEXT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE plg_glossary_glossary g
            SET g.explanation = COALESCE(
                (SELECT d.text FROM plg_glossary_definition d WHERE d.glossary_id = g.id AND d.language = ?),
                (SELECT d.text FROM plg_glossary_definition d WHERE d.glossary_id = g.id ORDER BY d.id LIMIT 1),
                ''
            )
            SQL, [$locale]);
        $this->addSql('ALTER TABLE plg_glossary_glossary MODIFY explanation LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE plg_glossary_definition DROP FOREIGN KEY FK_ECAC27B66ABB587D');
        $this->addSql('DROP TABLE plg_glossary_definition');
        $this->renameStoredField('definition_' . $locale, 'explanation');
    }

    private function sourceLocale(): string
    {
        $codes = $this->connection->fetchFirstColumn('SELECT code FROM language WHERE enabled = 1 ORDER BY sort_order');

        return in_array('en', $codes, true) ? 'en' : (string) ($codes[0] ?? 'en');
    }

    private function renameStoredField(string $from, string $to): void
    {
        foreach (['suggestion' => 'payload', 'change_proposal' => 'changes'] as $table => $column) {
            $rows = $this->connection->fetchAllAssociative(sprintf("SELECT id, %s AS data FROM %s WHERE target_type = 'glossary'", $column, $table));
            foreach ($rows as $row) {
                $data = json_decode((string) $row['data'], true);
                if (!is_array($data) || !array_key_exists($from, $data)) {
                    continue;
                }

                $data[$to] = $data[$from];
                unset($data[$from]);
                $this->addSql(sprintf('UPDATE %s SET %s = ? WHERE id = ?', $table, $column), [json_encode($data, JSON_UNESCAPED_UNICODE), (int) $row['id']]);
            }
        }
    }
}
