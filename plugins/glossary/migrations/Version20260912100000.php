<?php declare(strict_types=1);

namespace PluginGlossaryMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename glossary pinyin to secondary, add term_language, and rename the field inside stored glossary reviews';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_glossary_glossary CHANGE pinyin secondary VARCHAR(255) DEFAULT NULL, ADD term_language VARCHAR(5) DEFAULT NULL');
        $this->renameStoredField('pinyin', 'secondary');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_glossary_glossary CHANGE secondary pinyin VARCHAR(255) DEFAULT NULL, DROP term_language');
        $this->renameStoredField('secondary', 'pinyin');
    }

    private function renameStoredField(string $from, string $to): void
    {
        foreach ([$this->suggestionTable() => 'payload', 'change_proposal' => 'changes'] as $table => $column) {
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

    private function suggestionTable(): string
    {
        return $this->connection->createSchemaManager()->tablesExist(['mod_suggestion']) ? 'mod_suggestion' : 'suggestion';
    }
}
