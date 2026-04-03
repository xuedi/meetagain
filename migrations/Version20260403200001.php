<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403200001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed all standard languages (disabled by default); skip existing rows';
    }

    public function up(Schema $schema): void
    {
        // INSERT IGNORE skips rows where code already exists (unique constraint).
        // EN, DE, CN are already present on existing installs — they are safely skipped.
        $languages = [
            ['fr', 'French',      10],
            ['es', 'Spanish',     11],
            ['it', 'Italian',     12],
            ['pt', 'Portuguese',  13],
            ['nl', 'Dutch',       14],
            ['pl', 'Polish',      15],
            ['ru', 'Russian',     16],
            ['ja', 'Japanese',    17],
            ['ko', 'Korean',      18],
            ['ar', 'Arabic',      19],
            ['tr', 'Turkish',     20],
            ['sv', 'Swedish',     21],
            ['no', 'Norwegian',   22],
            ['da', 'Danish',      23],
            ['fi', 'Finnish',     24],
            ['uk', 'Ukrainian',   25],
            ['cs', 'Czech',       26],
            ['hu', 'Hungarian',   27],
            ['ro', 'Romanian',    28],
            ['el', 'Greek',       29],
            ['bg', 'Bulgarian',   30],
            ['hr', 'Croatian',    31],
            ['sk', 'Slovak',      32],
            ['hi', 'Hindi',       33],
            ['vi', 'Vietnamese',  34],
            ['id', 'Indonesian',  35],
            ['th', 'Thai',        36],
            ['ms', 'Malay',       37],
            ['he', 'Hebrew',      38],
            ['fa', 'Persian',     39],
            ['sr', 'Serbian',     40],
            ['sl', 'Slovenian',   41],
            ['lt', 'Lithuanian',  42],
            ['lv', 'Latvian',     43],
            ['et', 'Estonian',    44],
            ['is', 'Icelandic',   45],
            ['af', 'Afrikaans',   46],
            ['sw', 'Swahili',     47],
            ['bn', 'Bengali',     48],
            ['ur', 'Urdu',        49],
            ['ta', 'Tamil',       50],
            ['my', 'Burmese',     51],
            ['km', 'Khmer',       52],
            ['ka', 'Georgian',    53],
            ['hy', 'Armenian',    54],
            ['az', 'Azerbaijani', 55],
            ['be', 'Belarusian',  56],
            ['mk', 'Macedonian',  57],
            ['sq', 'Albanian',    58],
            ['mn', 'Mongolian',   59],
            ['si', 'Sinhala',     60],
            ['lo', 'Lao',         61],
            ['ga', 'Irish',       62],
            ['cy', 'Welsh',       63],
            ['mt', 'Maltese',     64],
            ['ca', 'Catalan',        65],
            ['eu', 'Basque',         66],
            ['gl', 'Galician',       67],
            ['lb', 'Luxembourgish',  68],
            ['rm', 'Romansh',        69],
            ['fo', 'Faroese',        70],
            ['fy', 'Frisian',        71],
        ];

        foreach ($languages as [$code, $name, $sortOrder]) {
            $this->addSql(
                'INSERT IGNORE INTO language (code, name, enabled, sort_order) VALUES (?, ?, 0, ?)',
                [$code, $name, $sortOrder],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $codes = ['fr','es','it','pt','nl','pl','ru','ja','ko','ar','tr','sv','no','da','fi',
                  'uk','cs','hu','ro','el','bg','hr','sk','hi','vi','id','th','ms','he','fa',
                  'sr','sl','lt','lv','et','is','af','sw','bn','ur','ta','my','km','ka','hy',
                  'az','be','mk','sq','mn','si','lo','ga','cy','mt','ca','eu','gl',
                  'lb','rm','fo','fy'];

        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $this->addSql("DELETE FROM language WHERE code IN ($placeholders)", $codes);
    }
}
