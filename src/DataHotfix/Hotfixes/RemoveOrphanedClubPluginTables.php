<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use Doctrine\DBAL\Connection;
use Override;

/**
 * Drops the orphaned tables left behind by the removed bookclub, dinnerclub and filmclub plugins
 * (superseded by the books, dishes and films item plugins). The only real data - the dinnerclub
 * dishes - was already copied into the plg_dishes_* schema by MigrateDinnerclubToDishes and is live;
 * everything else was test data. The source tables were never dropped, so they linger as dead weight.
 *
 * FK checks are disabled so the interdependent tables drop in any order; IF EXISTS makes it a no-op
 * on fresh installs and anywhere a table is already gone.
 */
readonly class RemoveOrphanedClubPluginTables implements DataHotfixInterface
{
    private const array ORPHANED_TABLES = [
        // bookclub -> books
        'book_note',
        'book_poll_vote',
        'book_poll',
        'book_selection',
        'book_suggestion',
        'book',
        // dinnerclub -> dishes
        'dinner_course_item',
        'dinner_course',
        'dinner',
        'dinnerclub_dish_image_suggestion',
        'dinnerclub_dish_image',
        'dish_translation',
        'dish_like',
        'dish_list',
        'dish',
        // filmclub -> films
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
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::ORPHANED_TABLES as $table) {
            $this->connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $table));
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
