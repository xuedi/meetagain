<?php declare(strict_types=1);

namespace App\Entity;

enum CommandEnum: string
{
    case clearCache = 'cache:clear';
    case executeMigrations = 'doctrine:migrations:migrate';
    case extractTranslations = 'translation:extract';

    public static function getCommands(): array
    {
        return [
            self::clearCache,
            self::executeMigrations,
            self::extractTranslations,
        ];
    }
}
