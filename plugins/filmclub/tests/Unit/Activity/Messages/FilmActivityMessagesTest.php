<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Activity\Messages;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Activity\Messages\FilmSelectedForEvent;
use Plugin\Filmclub\Activity\Messages\NoteAdded;
use Plugin\Filmclub\Activity\Messages\NoteRevealed;
use Plugin\Filmclub\Activity\Messages\PollClosed;
use Plugin\Filmclub\Activity\Messages\PollCreated;
use Plugin\Filmclub\Activity\Messages\PollVoteCast;
use Plugin\Filmclub\Activity\Messages\WishlistAdded;

class FilmActivityMessagesTest extends TestCase
{
    public static function provideTypeConstants(): iterable
    {
        yield 'NoteAdded' => [NoteAdded::TYPE, 'filmclub.note_added'];
        yield 'NoteRevealed' => [NoteRevealed::TYPE, 'filmclub.note_revealed'];
        yield 'PollCreated' => [PollCreated::TYPE, 'filmclub.poll_created'];
        yield 'PollClosed' => [PollClosed::TYPE, 'filmclub.poll_closed'];
        yield 'PollVoteCast' => [PollVoteCast::TYPE, 'filmclub.poll_vote_cast'];
        yield 'WishlistAdded' => [WishlistAdded::TYPE, 'filmclub.wishlist_added'];
        yield 'FilmSelectedForEvent' => [FilmSelectedForEvent::TYPE, 'filmclub.film_selected_for_event'];
    }

    #[DataProvider('provideTypeConstants')]
    public function testTypeConstantHasExpectedValue(string $actual, string $expected): void
    {
        // Arrange — done via DataProvider

        // Act — TYPE is a constant, reading it is the act

        // Assert
        static::assertSame($expected, $actual);
    }
}
