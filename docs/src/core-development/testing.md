# Testing

How to write and run tests for core application code.

---

## Test layout

```
tests/
├── Unit/                         ← mirrors src/ structure
│   ├── Service/
│   │   ├── ActivityServiceTest.php
│   │   ├── CleanupServiceTest.php
│   │   └── TranslationServiceTest.php
│   └── Entity/
│       └── EventTest.php
├── Functional/
│   └── Controller/
│       ├── EventControllerTest.php
│       └── SecurityControllerTest.php
├── DataFixtures/                 ← shared test fixtures (if needed)
├── phpunit.xml                   ← PHPUnit configuration
├── bootstrap.php                 ← test bootstrap
└── reports/
    ├── clover.xml                ← coverage report
    └── junit.xml                 ← JUnit results
```

`tests/Unit/` mirrors `src/` — if you add `src/Service/RatingService.php`, the test lives
at `tests/Unit/Service/RatingServiceTest.php`.

---

## AAA pattern

All tests follow **Arrange / Act / Assert** with explicit section comments.
The comments are required — they make the test intent immediately clear at a glance.

```php
namespace App\Tests\Unit\Service;

use App\Repository\TranslationRepository;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TranslationServiceTest extends TestCase
{
    private TranslationRepository $translationRepo;
    private TranslationService $subject;

    protected function setUp(): void
    {
        $this->translationRepo = $this->createStub(TranslationRepository::class);
        $em = $this->createStub(EntityManagerInterface::class);

        $this->subject = new TranslationService($this->translationRepo, $em);
    }

    public function testGetMatrixReturnsTranslationsGroupedByPlaceholder(): void
    {
        // Arrange: expected matrix structure
        $expected = [
            'action.save' => [
                'de' => ['id' => 1, 'value' => 'Speichern'],
                'en' => ['id' => 2, 'value' => 'Save'],
            ],
        ];

        // Arrange: stub repository to return the matrix
        $this->translationRepo
            ->method('getMatrix')
            ->willReturn($expected);

        // Act: call the method under test
        $actual = $this->subject->getMatrix();

        // Assert: matrix is correctly returned
        $this->assertEquals($expected, $actual);
    }
}
```

---

## Test doubles

### Stubs — provide canned return values

Use `createStub()` when you need a dependency to return a value but don't care whether
the method was called:

```php
protected function setUp(): void
{
    // Arrange: stub returns empty list by default
    $this->imageRepo = $this->createStub(ImageRepository::class);
    $this->em = $this->createStub(EntityManagerInterface::class);

    $this->subject = new CleanupService($this->imageRepo, $this->em);
}

public function testRemoveReturnsZeroWhenNoOrphans(): void
{
    // Arrange
    $this->imageRepo->method('findOrphaned')->willReturn([]);

    // Act
    $result = $this->subject->removeOrphanedImages();

    // Assert
    $this->assertSame(0, $result);
}
```

### Mocks — verify interactions

Use `createMock()` when you need to assert that a method *was called* (or called a specific
number of times):

```php
public function testRemoveOrphanedImagesCallsFlush(): void
{
    // Arrange: two orphaned images
    $this->imageRepo->method('findOrphaned')->willReturn([new Image(), new Image()]);

    // Arrange: mock EM to verify calls
    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->exactly(2))->method('remove');
    $em->expects($this->once())->method('flush');

    $subject = new CleanupService($this->imageRepo, $em);

    // Act + Assert
    $this->assertSame(2, $subject->removeOrphanedImages());
}
```

**Rule of thumb:**

- `createStub()` for dependencies you configure but don't verify
- `createMock()` only when you need `expects()` to verify a call was made

---

## Functional tests

Functional tests make real HTTP requests to the Symfony application. They use
`WebTestCase` and DAMA DoctrineTestBundle (all DB changes are rolled back after each test).

```php
namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventControllerTest extends WebTestCase
{
    public function testIndexDisplaysUpcomingEvents(): void
    {
        // Arrange: create an authenticated client
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy([]);
        $client->loginUser($user);

        // Act: request the events page
        $crawler = $client->request('GET', '/events');

        // Assert: successful response with content
        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('.event-card')->count());
    }

    public function testCreateEventRequiresAuthentication(): void
    {
        // Arrange: no login
        $client = static::createClient();

        // Act
        $client->request('GET', '/event/create');

        // Assert: redirect to login
        $this->assertResponseRedirects('/login');
    }
}
```

---

## Using fixtures in tests

Functional tests use the data created by the core fixture classes. The fixtures run once
before the test suite and each test rolls back its DB changes via a transaction.

Access fixture-created entities through the container:

```php
// Get a user that was created by UserFixture
$user = static::getContainer()
    ->get(UserRepository::class)
    ->findOneByEmail('admin@example.com');
```

For unit tests you don't need fixtures — use `createStub()` / `createMock()` instead.

See [Data Fixtures](fixtures.md) for how to write fixture classes and use `AbstractFixture`.

---

## Running tests

```bash
just testUnit                              # All unit tests
just testUnit tests/Unit/Service/          # Specific directory
just testUnit tests/Unit/Service/CleanupServiceTest.php  # Single file
just testFunctional                        # All functional tests
just test                                  # Full suite + quality checks
just testCoverage                          # HTML coverage report
just testPrintResults                      # AI-readable summary
just testPrintResults --failures-only      # Failures only
```

The internal `/test-unit` and `/test-functional` agent skills wrap these commands and
automatically use a small model to keep costs low.
