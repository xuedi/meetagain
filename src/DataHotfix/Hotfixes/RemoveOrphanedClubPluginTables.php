<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use Doctrine\DBAL\Connection;
use Override;

readonly class RemoveOrphanedClubPluginTables implements DataHotfixInterface
{
    private const array ORPHANED_TABLES = [
        'book_note',
        'book_poll_vote',
        'book_poll',
        'book_selection',
        'book_suggestion',
        'book',
        'dinner_course_item',
        'dinner_course',
        'dinner',
        'dinnerclub_dish_image_suggestion',
        'dinnerclub_dish_image',
        'dish_translation',
        'dish_like',
        'dish_list',
        'dish',
        'film_note',
        'film_poll_vote',
        'film_poll_films',
        'film_poll',
        'film_selection',
        'film_suggestion',
        'film_wishlist_entry',
        'filmclub_group_settings',
        'vote_ballot',
        'vote',
        'film',
    ];

    public function __construct(
        private Connection $connection,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_07_20_remove_orphaned_club_plugin_tables';
    }

    #[Override]
    public function execute(): void
    {
        // FK checks off so the interdependent tables drop in any order; IF EXISTS keeps it a no-op
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::ORPHANED_TABLES as $table) {
            $this->connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $table));
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
