<?php declare(strict_types=1);

namespace Plugin\Books;

use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Item\ItemAssociationService;
use App\ValueObject\LinkCollection;
use Plugin\Books\Service\BookService;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly BookService $bookService,
        private readonly ItemAssociationService $itemAssociations,
        private readonly EventRepository $eventRepository,
        private readonly UserRepository $userRepository,
    ) {}

    public function getPluginKey(): string
    {
        return 'books';
    }

    public function getLinkCollection(): LinkCollection
    {
        return LinkCollection::empty()->withNavLinks([
            new Link(slug: $this->urlGenerator->generate('app_books_booklist'), name: 'books.menu_main'),
        ]);
    }

    public function getEventTile(int $eventId, EventTileLocation $location): ?string
    {
        return null;
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
        if ($this->bookService->getList() !== []) {
            $output->writeln('<comment>Books: already seeded, skipping.</comment>');

            return;
        }

        $admin = $this->userRepository->findOneBy(['email' => 'admin@example.org']);
        if ($admin === null) {
            $output->writeln('<comment>Books: no admin user found, skipping.</comment>');

            return;
        }
        $adminId = (int) $admin->getId();

        $catalog = [
            ['978-0143105428', 'The Odyssey',           'Homer',           'The wanderings of Odysseus on his way home from Troy.',      541, 2006],
            ['978-0141439518', 'Pride and Prejudice',   'Jane Austen',     'Elizabeth Bennet navigates manners, morality and marriage.', 480, 2003],
            ['978-0060850524', 'Brave New World',       'Aldous Huxley',   'A dystopia of engineered contentment.',                      288, 2006],
            ['978-0060935467', 'To Kill a Mockingbird', 'Harper Lee',      'A lawyer defends a black man in the Depression-era South.',  336, 2002],
            ['978-0143039433', 'East of Eden',          'John Steinbeck',  'Two families retrace the story of the fall from Eden.',      601, 2002],
            ['978-0140283334', 'On the Road',           'Jack Kerouac',    'A restless cross-country search for meaning.',               307, 1999],
            ['978-0140177398', 'Of Mice and Men',       'John Steinbeck',  'Two drifters chase a dream during the Great Depression.',    112, 1993],
            ['978-0142437209', 'Moby-Dick',             'Herman Melville', 'Captain Ahab pursues the white whale.',                      720, 2003],
        ];

        $created = [];
        foreach ($catalog as [$isbn, $title, $author, $description, $pageCount, $publishedYear]) {
            $created[] = $this->bookService->createManual($isbn, $title, $author, $description, $pageCount, $publishedYear, $adminId);
        }

        // Attach several books across the available events (multiple per event when past events
        // are few), giving the event pages and the item list real associations.
        $events = $this->eventRepository->getPastEvents(4);
        if ($events === []) {
            $events = $this->eventRepository->getUpcomingEvents(4);
        }
        $attached = 0;
        foreach ($created as $index => $book) {
            if ($events === [] || $attached >= 4) {
                break;
            }
            $event = $events[$index % count($events)];
            $this->itemAssociations->attach((int) $event->getId(), BookService::ITEM_TYPE, (int) $book->getId(), $adminId, $index);
            $attached++;
        }

        $output->writeln(sprintf('<info>Books: seeded %d books, attached %d to events.</info>', count($created), $attached));
    }

    public function preFixtures(OutputInterface $output): void {}

    public function postFixtures(OutputInterface $output): void {}

    public function getFooterAbout(): ?string
    {
        return null;
    }

    public function getEventListItemTags(int $eventId): array
    {
        return [];
    }

    public function warmCache(WarmCacheType $type, array $ids): void {}

    public function getStylesheets(): array
    {
        return [];
    }

    public function getJavascripts(): array
    {
        return [];
    }
}
