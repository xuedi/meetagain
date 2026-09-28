<?php declare(strict_types=1);

namespace PluginGlossaryMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Glossary creation moves onto the universal suggestion tool, so the bespoke approval flag goes.
 *
 * Rows still waiting for approval are converted into pending suggestions rather than approved:
 * they exist because nobody had decided yet, and dropping the column with the rows in place would
 * publish member text no reviewer ever read. The payload is the same shape GlossaryTarget stores,
 * so the converted row opens in the reviewer's normal queue. An entry whose author no longer has a
 * user row cannot be attributed and is dropped with the rest.
 */
final class Version20260906130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert unapproved glossary entries into pending suggestions and drop the approved column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(sprintf(<<<'SQL'
            INSERT INTO %s (target_type, payload, proposed_by_id, status, created_at)
            SELECT 'glossary',
                   JSON_OBJECT('phrase', g.phrase, 'pinyin', g.pinyin, 'explanation', g.explanation),
                   g.created_by,
                   'pending',
                   g.created_at
            FROM plg_glossary_glossary g
            INNER JOIN user u ON u.id = g.created_by
            WHERE g.approved = 0
            SQL, $this->suggestionTable()));

        $this->addSql(<<<'SQL'
            DELETE FROM item_tag_assignment
            WHERE item_type = 'glossary'
              AND item_id IN (SELECT id FROM (SELECT id FROM plg_glossary_glossary WHERE approved = 0) AS unapproved)
            SQL);

        $this->addSql(<<<'SQL'
            DELETE FROM change_proposal
            WHERE target_type = 'glossary'
              AND target_id IN (SELECT id FROM (SELECT id FROM plg_glossary_glossary WHERE approved = 0) AS unapproved)
            SQL);

        $this->addSql('DELETE FROM plg_glossary_glossary WHERE approved = 0');
        $this->addSql('ALTER TABLE plg_glossary_glossary DROP approved');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_glossary_glossary ADD approved TINYINT(1) DEFAULT 1 NOT NULL');
    }

    private function suggestionTable(): string
    {
        return $this->connection->createSchemaManager()->tablesExist(['mod_suggestion']) ? 'mod_suggestion' : 'suggestion';
    }
}
