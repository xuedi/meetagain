<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404200001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pronunciation_system table and seed standard systems';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE pronunciation_system (
            id INT AUTO_INCREMENT NOT NULL,
            language VARCHAR(100) NOT NULL,
            name VARCHAR(100) NOT NULL,
            example VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $systems = [
            ['Chinese / Mandarin', 'Pinyin',               'má po dòu fu'],
            ['Japanese',           'Rōmaji',               'ra-men'],
            ['Arabic',             'Romanisation',         'kus-kus'],
            ['Korean',             'Revised Romanisation', 'bi-bim-bap'],
            ['Thai',               'RTGS',                 'phàt thai'],
            ['Hindi',              'IAST',                 'bi-ryā-nī'],
            ['Greek',              'Greeklish',            'mu-sa-kás'],
        ];

        foreach ($systems as [$language, $name, $example]) {
            $this->addSql(
                'INSERT INTO pronunciation_system (language, name, example) VALUES (?, ?, ?)',
                [$language, $name, $example],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pronunciation_system');
    }
}
