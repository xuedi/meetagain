<?php declare(strict_types=1);

namespace Plugin\Bookclub\DataFixtures;

use App\DataFixtures\AbstractFixture;
use App\Entity\ImageType;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ImageService;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Entity\BookNote;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BookclubFixture extends AbstractFixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly UserRepository $userRepository,
    ) {}

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating bookclub data ... ';

        $importUser = $this->userRepository->findOneBy(['email' => 'import@example.com']);
        if (!$importUser instanceof User) {
            echo 'SKIP (import user not found)' . PHP_EOL;
            return;
        }

        $books = $this->createBooks($manager, $importUser);
        $this->createUserNotes($manager, $books);

        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    /**
     * @return Book[]
     */
    private function createBooks(ObjectManager $manager, User $importUser): array
    {
        $bookData = $this->getBookData();
        $totalBooks = count($bookData);
        $pendingFromIndex = $totalBooks - 3; // last 3 books are pending approval

        $userCache = [];
        $books = [];
        foreach ($bookData as $index => $data) {
            $name = $data['createdBy'];
            if (!isset($userCache[$name])) {
                $userCache[$name] = $this->userRepository->findOneBy(['name' => $name]);
            }
            $createdBy = $userCache[$name] ?? $importUser;

            $book = new Book();
            $book->setIsbn($data['isbn']);
            $book->setTitle($data['title']);
            $book->setAuthor($data['author']);
            $book->setDescription($data['description']);
            $book->setPageCount($data['pageCount']);
            $book->setPublishedYear($data['publishedYear']);
            $book->setApproved($index < $pendingFromIndex);
            $book->setCreatedBy($createdBy->getId());
            $book->setCreatedAt(new DateTimeImmutable('-' . rand(30, 365) . ' days'));

            // Add cover image if available
            $coverFile = __DIR__ . '/covers/' . $data['isbn'] . '.jpg';
            if (file_exists($coverFile)) {
                $uploadedImage = new UploadedFile($coverFile, $data['isbn'] . '.jpg');
                $image = $this->imageService->upload($uploadedImage, $importUser, ImageType::PluginBookclubCover);
                $this->imageService->createThumbnails($image, ImageType::PluginBookclubCover);
                $book->setCoverImage($image);
            }

            $manager->persist($book);
            $books[] = $book;
        }

        return $books;
    }

    /** @param Book[] $books */
    private function createUserNotes(ObjectManager $manager, array $books): void
    {
        $noteContents = [
            "Really enjoyed this book. The author's writing style is captivating and the plot kept me engaged throughout.",
            'Interesting perspective on the subject matter. Some parts were slow but overall a worthwhile read.',
            "Couldn't put it down! The character development was excellent and the ending was satisfying.",
            'This book made me think about things differently. Highly recommend for book club discussions.',
            'A bit dense in places but the themes are thought-provoking. Good choice for our group.',
            'Loved the historical context. The author did extensive research and it shows.',
            'Not my favorite genre but I can see why others enjoy it. Well-written nonetheless.',
            'Perfect balance of entertainment and depth. Will definitely read more from this author.',
            'The metaphors were a bit heavy-handed but the core message resonates.',
            'Surprised by how much I enjoyed this. Great recommendation from the group!',
        ];

        // Create notes for the first 15 books, spread across users 1-10
        for ($i = 0; $i < 15; $i++) {
            $userId = ($i % 10) + 1;
            $book = $books[$i];

            $note = new BookNote();
            $note->setBook($book);
            $note->setUserId($userId);
            $note->setContent($noteContents[$i % count($noteContents)]);
            $note->setCreatedAt(new DateTimeImmutable('-' . rand(1, 30) . ' days'));

            if (rand(0, 1) === 1) {
                $note->setUpdatedAt(new DateTimeImmutable('-' . rand(0, 5) . ' days'));
            }

            $manager->persist($note);
        }

        // Add a few more notes from different users on different books (avoid duplicates)
        $extraBookIndices = [20, 25, 30]; // Different books to avoid user+book conflicts
        foreach ($extraBookIndices as $index => $bookIndex) {
            for ($userId = 3; $userId <= 5; $userId++) {
                $note = new BookNote();
                $note->setBook($books[$bookIndex]);
                $note->setUserId($userId);
                $note->setContent($noteContents[rand(0, count($noteContents) - 1)]);
                $note->setCreatedAt(new DateTimeImmutable('-' . rand(5, 20) . ' days'));
                $manager->persist($note);
            }
        }
    }

    private function getBookData(): array
    {
        return [
            ['isbn' => '978-0141439518', 'title' => 'Pride and Prejudice', 'author' => 'Jane Austen', 'description' => 'A romantic novel following the emotional development of Elizabeth Bennet.', 'pageCount' => 432, 'publishedYear' => 1813, 'createdBy' => 'Amelie Laurent'],
            ['isbn' => '978-0451524935', 'title' => '1984', 'author' => 'George Orwell', 'description' => 'A dystopian novel set in a totalitarian society ruled by Big Brother.', 'pageCount' => 328, 'publishedYear' => 1949, 'createdBy' => 'Blake Riley'],
            ['isbn' => '978-0060850524', 'title' => 'Brave New World', 'author' => 'Aldous Huxley', 'description' => 'A futuristic society where humans are genetically modified and socially indoctrinated.', 'pageCount' => 288, 'publishedYear' => 1932, 'createdBy' => 'Cameron Yang'],
            ['isbn' => '978-0743273565', 'title' => 'The Great Gatsby', 'author' => 'F. Scott Fitzgerald', 'description' => 'A tale of wealth, love, and the American Dream in the Jazz Age.', 'pageCount' => 180, 'publishedYear' => 1925, 'createdBy' => 'Demi Wilkinson'],
            ['isbn' => '978-0061120084', 'title' => 'To Kill a Mockingbird', 'author' => 'Harper Lee', 'description' => "A story of racial injustice in the American South through a child's eyes.", 'pageCount' => 336, 'publishedYear' => 1960, 'createdBy' => 'Elena Owens'],
            ['isbn' => '978-0140283334', 'title' => 'On the Road', 'author' => 'Jack Kerouac', 'description' => 'A beat generation classic about cross-country adventures.', 'pageCount' => 320, 'publishedYear' => 1957, 'createdBy' => 'Fergus Gray'],
            ['isbn' => '978-0140449136', 'title' => 'Crime and Punishment', 'author' => 'Fyodor Dostoevsky', 'description' => 'A psychological drama about a young man who commits murder.', 'pageCount' => 671, 'publishedYear' => 1866, 'createdBy' => 'Fleur Cook'],
            ['isbn' => '978-0679720201', 'title' => 'The Stranger', 'author' => 'Albert Camus', 'description' => 'An existentialist novel about a man who kills an Arab on a beach.', 'pageCount' => 123, 'publishedYear' => 1942, 'createdBy' => 'Harriet Rojas'],
            ['isbn' => '978-0142437209', 'title' => 'Don Quixote', 'author' => 'Miguel de Cervantes', 'description' => 'The adventures of a man who believes himself to be a knight.', 'pageCount' => 1072, 'publishedYear' => 1605, 'createdBy' => 'Harry Bender'],
            ['isbn' => '978-0141439600', 'title' => 'Jane Eyre', 'author' => 'Charlotte Bronte', 'description' => 'A bildungsroman following the experiences of an orphan governess.', 'pageCount' => 532, 'publishedYear' => 1847, 'createdBy' => 'Amelie Bennett'],
            ['isbn' => '978-0143105428', 'title' => 'Anna Karenina', 'author' => 'Leo Tolstoy', 'description' => 'A tragic love story set in Russian high society.', 'pageCount' => 864, 'publishedYear' => 1877, 'createdBy' => 'Blake Riley'],
            ['isbn' => '978-0679723165', 'title' => 'One Hundred Years of Solitude', 'author' => 'Gabriel Garcia Marquez', 'description' => 'A multi-generational saga of the Buendia family in Macondo.', 'pageCount' => 417, 'publishedYear' => 1967, 'createdBy' => 'Cameron Yang'],
            ['isbn' => '978-0140449266', 'title' => 'The Brothers Karamazov', 'author' => 'Fyodor Dostoevsky', 'description' => 'A philosophical novel about faith, doubt, and reason.', 'pageCount' => 796, 'publishedYear' => 1880, 'createdBy' => 'Demi Wilkinson'],
            ['isbn' => '978-0140187397', 'title' => 'Of Mice and Men', 'author' => 'John Steinbeck', 'description' => 'A novella about two displaced migrant ranch workers.', 'pageCount' => 112, 'publishedYear' => 1937, 'createdBy' => 'Elena Owens'],
            ['isbn' => '978-0684801223', 'title' => 'The Old Man and the Sea', 'author' => 'Ernest Hemingway', 'description' => "An aging fisherman's struggle with a giant marlin.", 'pageCount' => 127, 'publishedYear' => 1952, 'createdBy' => 'Fergus Gray'],
            ['isbn' => '978-0140283297', 'title' => 'Slaughterhouse-Five', 'author' => 'Kurt Vonnegut', 'description' => 'A satirical novel about World War II and time travel.', 'pageCount' => 275, 'publishedYear' => 1969, 'createdBy' => 'Fleur Cook'],
            ['isbn' => '978-0679735779', 'title' => 'Lolita', 'author' => 'Vladimir Nabokov', 'description' => 'A controversial novel about obsession and manipulation.', 'pageCount' => 317, 'publishedYear' => 1955, 'createdBy' => 'Harriet Rojas'],
            ['isbn' => '978-0060935467', 'title' => 'To the Lighthouse', 'author' => 'Virginia Woolf', 'description' => 'A modernist novel exploring consciousness and perception.', 'pageCount' => 209, 'publishedYear' => 1927, 'createdBy' => 'Harry Bender'],
            ['isbn' => '978-0140449327', 'title' => 'War and Peace', 'author' => 'Leo Tolstoy', 'description' => 'An epic novel of Russian society during the Napoleonic Era.', 'pageCount' => 1225, 'publishedYear' => 1869, 'createdBy' => 'Amelie Bennett'],
            ['isbn' => '978-0140449174', 'title' => 'Moby-Dick', 'author' => 'Herman Melville', 'description' => "Captain Ahab's obsessive quest for the white whale.", 'pageCount' => 720, 'publishedYear' => 1851, 'createdBy' => 'Amelie Laurent'],
            ['isbn' => '978-0140620627', 'title' => 'Wuthering Heights', 'author' => 'Emily Bronte', 'description' => 'A tale of passionate and destructive love on the Yorkshire moors.', 'pageCount' => 416, 'publishedYear' => 1847, 'createdBy' => 'Blake Riley'],
            ['isbn' => '978-0143039433', 'title' => 'The Grapes of Wrath', 'author' => 'John Steinbeck', 'description' => "A family's journey during the Great Depression and Dust Bowl.", 'pageCount' => 464, 'publishedYear' => 1939, 'createdBy' => 'Cameron Yang'],
            ['isbn' => '978-0679732761', 'title' => 'Invisible Man', 'author' => 'Ralph Ellison', 'description' => "An African American man's search for identity in a hostile society.", 'pageCount' => 581, 'publishedYear' => 1952, 'createdBy' => 'Demi Wilkinson'],
            ['isbn' => '978-0679728023', 'title' => 'Beloved', 'author' => 'Toni Morrison', 'description' => 'A powerful novel about the legacy of slavery.', 'pageCount' => 324, 'publishedYear' => 1987, 'createdBy' => 'Elena Owens'],
            ['isbn' => '978-0060929879', 'title' => 'Catch-22', 'author' => 'Joseph Heller', 'description' => 'A satirical novel about the absurdity of war.', 'pageCount' => 453, 'publishedYear' => 1961, 'createdBy' => 'Fergus Gray'],
            ['isbn' => '978-0143106586', 'title' => 'East of Eden', 'author' => 'John Steinbeck', 'description' => "A family saga set in California's Salinas Valley.", 'pageCount' => 601, 'publishedYear' => 1952, 'createdBy' => 'Fleur Cook'],
            ['isbn' => '978-0140243727', 'title' => 'The Sound and the Fury', 'author' => 'William Faulkner', 'description' => 'The decline of a Southern aristocratic family.', 'pageCount' => 326, 'publishedYear' => 1929, 'createdBy' => 'Harriet Rojas'],
            ['isbn' => '978-0679734529', 'title' => 'A Clockwork Orange', 'author' => 'Anthony Burgess', 'description' => 'A dystopian novel about youth violence and free will.', 'pageCount' => 192, 'publishedYear' => 1962, 'createdBy' => 'Harry Bender'],
            ['isbn' => '978-0060850517', 'title' => 'Fahrenheit 451', 'author' => 'Ray Bradbury', 'description' => 'A dystopian novel about a future where books are banned.', 'pageCount' => 194, 'publishedYear' => 1953, 'createdBy' => 'Amelie Bennett'],
            ['isbn' => '978-0140177398', 'title' => "The Handmaid's Tale", 'author' => 'Margaret Atwood', 'description' => 'A dystopian novel about a totalitarian theocracy.', 'pageCount' => 311, 'publishedYear' => 1985, 'createdBy' => 'Amelie Laurent'],
            ['isbn' => '978-0553382563', 'title' => 'A Game of Thrones', 'author' => 'George R.R. Martin', 'description' => 'Epic fantasy of political intrigue and war.', 'pageCount' => 835, 'publishedYear' => 1996, 'createdBy' => 'Blake Riley'],
            ['isbn' => '978-0547928227', 'title' => 'The Hobbit', 'author' => 'J.R.R. Tolkien', 'description' => "Bilbo Baggins' unexpected journey with dwarves.", 'pageCount' => 310, 'publishedYear' => 1937, 'createdBy' => 'Cameron Yang'],
            ['isbn' => '978-0544003415', 'title' => 'The Lord of the Rings', 'author' => 'J.R.R. Tolkien', 'description' => 'The epic quest to destroy the One Ring.', 'pageCount' => 1178, 'publishedYear' => 1954, 'createdBy' => 'Demi Wilkinson'],
            ['isbn' => '978-0062315007', 'title' => 'The Alchemist', 'author' => 'Paulo Coelho', 'description' => "A shepherd boy's journey to find treasure in Egypt.", 'pageCount' => 208, 'publishedYear' => 1988, 'createdBy' => 'Elena Owens'],
            ['isbn' => '978-0316769488', 'title' => 'The Catcher in the Rye', 'author' => 'J.D. Salinger', 'description' => "Holden Caulfield's experiences in New York City.", 'pageCount' => 277, 'publishedYear' => 1951, 'createdBy' => 'Fergus Gray'],
            ['isbn' => '978-0385333481', 'title' => 'The Kite Runner', 'author' => 'Khaled Hosseini', 'description' => 'A story of friendship and redemption in Afghanistan.', 'pageCount' => 371, 'publishedYear' => 2003, 'createdBy' => 'Fleur Cook'],
            ['isbn' => '978-0307474278', 'title' => 'The Road', 'author' => 'Cormac McCarthy', 'description' => "A father and son's journey through post-apocalyptic America.", 'pageCount' => 287, 'publishedYear' => 2006, 'createdBy' => 'Harriet Rojas'],
            ['isbn' => '978-0060531041', 'title' => 'The Hours', 'author' => 'Michael Cunningham', 'description' => "Three women connected by Virginia Woolf's Mrs Dalloway.", 'pageCount' => 230, 'publishedYear' => 1998, 'createdBy' => 'Harry Bender'],
            ['isbn' => '978-0143034902', 'title' => 'Atonement', 'author' => 'Ian McEwan', 'description' => 'A story of love, war, and the power of storytelling.', 'pageCount' => 351, 'publishedYear' => 2001, 'createdBy' => 'Amelie Bennett'],
            ['isbn' => '978-0679745587', 'title' => 'Norwegian Wood', 'author' => 'Haruki Murakami', 'description' => 'A nostalgic story of loss and sexuality in 1960s Japan.', 'pageCount' => 296, 'publishedYear' => 1987, 'createdBy' => 'Amelie Laurent'],
            ['isbn' => '978-0307387899', 'title' => 'The Girl with the Dragon Tattoo', 'author' => 'Stieg Larsson', 'description' => 'A journalist and hacker investigate a decades-old disappearance.', 'pageCount' => 465, 'publishedYear' => 2005, 'createdBy' => 'Blake Riley'],
            ['isbn' => '978-0385490818', 'title' => 'Life of Pi', 'author' => 'Yann Martel', 'description' => 'A young man survives a shipwreck and shares a lifeboat with a Bengal tiger.', 'pageCount' => 319, 'publishedYear' => 2001, 'createdBy' => 'Cameron Yang'],
            ['isbn' => '978-0525478812', 'title' => 'The Fault in Our Stars', 'author' => 'John Green', 'description' => 'Two teenagers with cancer fall in love.', 'pageCount' => 313, 'publishedYear' => 2012, 'createdBy' => 'Demi Wilkinson'],
            ['isbn' => '978-0307949486', 'title' => 'Gone Girl', 'author' => 'Gillian Flynn', 'description' => 'A wife disappears and her husband becomes the prime suspect.', 'pageCount' => 415, 'publishedYear' => 2012, 'createdBy' => 'Elena Owens'],
            ['isbn' => '978-0316055437', 'title' => 'The Lovely Bones', 'author' => 'Alice Sebold', 'description' => 'A murdered girl watches from heaven as her family copes.', 'pageCount' => 328, 'publishedYear' => 2002, 'createdBy' => 'Fergus Gray'],
            ['isbn' => '978-0385504201', 'title' => 'The Da Vinci Code', 'author' => 'Dan Brown', 'description' => 'A symbologist uncovers a conspiracy about the Holy Grail.', 'pageCount' => 489, 'publishedYear' => 2003, 'createdBy' => 'Fleur Cook'],
            // Tech / non-fiction books
            ['isbn' => '978-0132350884', 'title' => 'Clean Code', 'author' => 'Robert C. Martin', 'description' => 'A handbook of agile software craftsmanship covering clean code principles, naming, functions, and refactoring.', 'pageCount' => 431, 'publishedYear' => 2008, 'createdBy' => 'Adem Lane'],
            ['isbn' => '978-0135957059', 'title' => 'The Pragmatic Programmer', 'author' => 'Hunt & Thomas', 'description' => 'Your journey to mastery — timeless lessons on career development, coding practices, and software craftsmanship.', 'pageCount' => 352, 'publishedYear' => 2019, 'createdBy' => 'Orlando Diggs'],
            ['isbn' => '978-1449373320', 'title' => 'Designing Data-Intensive Applications', 'author' => 'Martin Kleppmann', 'description' => 'The big ideas behind reliable, scalable, and maintainable systems.', 'pageCount' => 590, 'publishedYear' => 2017, 'createdBy' => 'Crystal Liu'],
            ['isbn' => '978-0201835953', 'title' => 'The Mythical Man-Month', 'author' => 'Frederick P. Brooks', 'description' => 'Essays on software engineering exploring why adding manpower to a late software project makes it later.', 'pageCount' => 322, 'publishedYear' => 1995, 'createdBy' => 'Phoenix Baker'],
            ['isbn' => '978-0262510875', 'title' => 'Structure and Interpretation of Computer Programs', 'author' => 'Abelson & Sussman', 'description' => 'A classic MIT text on computational thinking through the Scheme programming language.', 'pageCount' => 657, 'publishedYear' => 1996, 'createdBy' => 'Candice Wu'],
            ['isbn' => '978-0735619678', 'title' => 'Code Complete', 'author' => 'Steve McConnell', 'description' => 'A comprehensive guide to software construction — the practical backbone of software engineering.', 'pageCount' => 914, 'publishedYear' => 2004, 'createdBy' => 'Drew Cano'],
            ['isbn' => '978-0134757599', 'title' => 'Refactoring', 'author' => 'Martin Fowler', 'description' => 'Improving the design of existing code through disciplined technique and a catalog of refactorings.', 'pageCount' => 448, 'publishedYear' => 2018, 'createdBy' => 'Kate Morrison'],
            ['isbn' => '978-0321125217', 'title' => 'Domain-Driven Design', 'author' => 'Eric Evans', 'description' => 'Tackling complexity in the heart of software through model-driven design and ubiquitous language.', 'pageCount' => 560, 'publishedYear' => 2003, 'createdBy' => 'Adem Lane'],
            ['isbn' => '978-1680502398', 'title' => 'Release It!', 'author' => 'Michael T. Nygard', 'description' => 'Design and deploy production-ready software with patterns for stability, capacity, and resilience.', 'pageCount' => 376, 'publishedYear' => 2018, 'createdBy' => 'Orlando Diggs'],
            ['isbn' => '978-1736417911', 'title' => 'Staff Engineer', 'author' => 'Will Larson', 'description' => 'Leadership beyond the management track — a guide for engineers operating at staff level and above.', 'pageCount' => 187, 'publishedYear' => 2021, 'createdBy' => 'Crystal Liu'],
            // The following 3 books are pending approval
            ['isbn' => '978-0439064873', 'title' => 'Harry Potter and the Chamber of Secrets', 'author' => 'J.K. Rowling', 'description' => "Harry's second year at Hogwarts reveals dark secrets.", 'pageCount' => 341, 'publishedYear' => 1998, 'createdBy' => 'Harriet Rojas'],
            ['isbn' => '978-0439136365', 'title' => 'Harry Potter and the Prisoner of Azkaban', 'author' => 'J.K. Rowling', 'description' => 'Harry learns about his godfather Sirius Black.', 'pageCount' => 435, 'publishedYear' => 1999, 'createdBy' => 'Harry Bender'],
            ['isbn' => '978-0307588371', 'title' => 'Mockingjay', 'author' => 'Suzanne Collins', 'description' => 'The final book in the Hunger Games trilogy.', 'pageCount' => 390, 'publishedYear' => 2010, 'createdBy' => 'Amelie Bennett'],
        ];
    }

    public static function getGroups(): array
    {
        return ['plugin'];
    }
}
