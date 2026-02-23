# Testing Guidelines

Testing strategies and patterns for the MeetAgain application.

---

## Overview

**Testing Framework:** PHPUnit 12.5+
**Test Organization:** Separate Unit and Functional test suites
**Database:** DAMA DoctrineTestBundle for transaction rollback
**Coverage Target:** 80%+ (enforced by CI)

---

## Test Organization

```
tests/
├── Unit/
│   ├── Service/          # Service layer tests
│   └── Entity/           # Entity logic tests
├── Functional/
│   └── Controller/       # Controller integration tests
├── DataFixtures/         # Shared test fixtures
├── phpunit.xml          # PHPUnit configuration
├── bootstrap.php        # Test bootstrap
└── reports/
    ├── clover.xml       # Coverage report
    └── junit.xml        # JUnit test results
```

---

## PHPUnit 12 Configuration

From `tests/phpunit.xml`:

```xml
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true"
         displayDetailsOnAllIssues="true">
    <testsuites>
        <testsuite name="default">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="functional">
            <directory>tests/Functional</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

**Key settings:**
- `failOnRisky="true"` - Fail on risky tests
- `failOnWarning="true"` - Fail on warnings
- `displayDetailsOnAllIssues="true"` - Show full error details

---

## Unit Tests

### AAA Pattern

Always use Arrange-Act-Assert with comments:

```php
class TranslationServiceTest extends TestCase
{
    public function testGetMatrixReturnsTranslationsGroupedByPlaceholderAndLanguage(): void
    {
        // Arrange: expected matrix structure sorted alphabetically by placeholder
        $expected = [
            'a_translation' => [
                'de' => ['id' => 1, 'value' => 'Translation A-DE'],
                'en' => ['id' => 2, 'value' => 'Translation A-EN'],
            ],
        ];

        // Arrange: mock repository to return matrix
        $this->translationRepo = $this->createMock(TranslationRepository::class);
        $this->translationRepo
            ->expects($this->once())
            ->method('getMatrix')
            ->willReturn($expected);

        // Act: get matrix
        $actual = $this->subject->getMatrix();

        // Assert: matrix is correctly structured and sorted
        $this->assertEquals($expected, $actual);
    }
}
```

---

### Test Doubles: Stub vs Mock

**Use `createStub()` for dependencies:**
```php
protected function setUp(): void
{
    $this->translationRepo = $this->createStub(TranslationRepository::class);
    $this->entityManager = $this->createStub(EntityManagerInterface::class);

    $this->subject = new TranslationService(
        $this->translationRepo,
        $this->entityManager,
    );
}
```

**Use `createMock()` when verifying interactions:**
```php
public function testSaveMatrixPersistsChanges(): void
{
    // Arrange
    $this->entityManager = $this->createMock(EntityManagerInterface::class);

    // Assert: persist should be called once
    $this->entityManager->expects($this->once())->method('persist');
    $this->entityManager->expects($this->once())->method('flush');

    // Act
    $this->subject->saveMatrix($request);
}
```

---

### Data Providers

Use PHPUnit attributes (not annotations):

```php
use PHPUnit\Framework\Attributes\DataProvider;

class EventServiceTest extends TestCase
{
    #[DataProvider('eventTypeProvider')]
    public function testFilterByType(EventTypes $type, int $expectedCount): void
    {
        // Arrange
        $events = $this->createEventsWithTypes();

        // Act
        $filtered = $this->subject->filterByType($events, $type);

        // Assert
        $this->assertCount($expectedCount, $filtered);
    }

    public static function eventTypeProvider(): array
    {
        return [
            'all types' => [EventTypes::All, 10],
            'meetings only' => [EventTypes::Meeting, 5],
            'social only' => [EventTypes::Social, 5],
        ];
    }
}
```

---

### Testing Services

```php
class CleanupServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $this->imageRepo = $this->createStub(ImageRepository::class);
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->subject = new CleanupService($this->imageRepo, $this->em);
    }

    public function testRemoveOrphanedImagesDeletesOrphanedImages(): void
    {
        // Arrange: stub returns 2 orphaned images; mock expects 2 removes + 1 flush
        $this->imageRepo->method('findOrphaned')->willReturn([new Image(), new Image()]);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->exactly(2))->method('remove');
        $this->em->expects($this->once())->method('flush');
        $this->subject = new CleanupService($this->imageRepo, $this->em);

        // Act + Assert
        $this->assertSame(2, $this->subject->removeOrphanedImages());
    }
}
```

---

## Functional Tests

### Controller Testing

Use WebTestCase with DAMA DoctrineTestBundle:

```php
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testIndexDisplaysUpcomingEvents(): void
    {
        // Arrange: authenticate user
        $user = $this->getUser();
        $this->client->loginUser($user);

        // Act: request page
        $crawler = $this->client->request('GET', '/events');

        // Assert: successful response
        $this->assertResponseIsSuccessful();

        // Assert: contains event elements
        $this->assertGreaterThan(0, $crawler->filter('.event-card')->count());
    }

    public function testCreateEventRequiresAuthentication(): void
    {
        // Act: request without authentication
        $this->client->request('GET', '/event/create');

        // Assert: redirect to login
        $this->assertResponseRedirects('/login');
    }
}
```

---

### Form Submission Testing

```php
public function testCreateEventWithValidData(): void
{
    // Arrange: authenticate
    $this->client->loginUser($this->getUser());

    // Act: submit form
    $crawler = $this->client->request('GET', '/event/create');
    $form = $crawler->selectButton('Save')->form([
        'event[title]' => 'Test Event',
        'event[description]' => 'Test Description',
        'event[start]' => '2026-12-31 18:00',
    ]);
    $this->client->submit($form);

    // Assert: redirect to event details
    $this->assertResponseRedirects();
    $this->client->followRedirect();
    $this->assertSelectorTextContains('h1', 'Test Event');
}
```

---

## Fixtures

### Custom AbstractFixture

This project uses a custom `AbstractFixture` with type-safe magic methods for managing references.

**Quick Example:**
```php
use App\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class EventFixture extends AbstractFixture implements DependentFixtureInterface
{
    public const string WEDNESDAY_MEETUP = 'Regular Wednesday meetup';

    public function load(ObjectManager $manager): void
    {
        $this->start();  // Helper: prints "Creating Event ..."

        $event = new Event();
        $event->setTitle(self::WEDNESDAY_MEETUP);
        $event->setStart(new DateTimeImmutable('+1 week'));

        // ✅ Custom reference system - type-safe
        $event->setUser($this->getRefUser('john_doe'));
        $event->setLocation($this->getRefLocation('office'));
        $event->addHost($this->getRefHost('engineering_team'));

        $manager->persist($event);
        $this->addRefEvent(self::WEDNESDAY_MEETUP, $event);

        $manager->flush();
        $this->stop();   // Helper: prints " OK\n"
    }

    public function getDependencies(): array
    {
        return [UserFixture::class, LocationFixture::class, HostFixture::class];
    }
}
```

**Quick Reference - Available Methods:**
```php
// Magic methods (via __call):
$user = $this->getRefUser('john_doe');      // Get User by name
$this->addRefUser('jane_doe', $userEntity); // Store User reference
// Also: getRefHost, getRefLocation, getRefCms, getRefEvent

// Helper methods:
$this->start();                      // Progress: "Creating FixtureName ..."
$this->stop();                       // Progress: " OK\n"
$text = $this->getText('filename');  // Read DataFixtures/FixtureName/filename.txt
```


---

## Coverage

### Running Coverage

```bash
just testCoverage              # Generate coverage and show report
just testCoverage --threshold=80  # Show files below 80% coverage
```
Output:
```
COVERAGE: 85% (1234/1450)
---
NEEDS ATTENTION:
  75% ImageService.php (25 uncov) - MED
  60% TranslationService.php (40 uncov) - HIGH
```

---

### Coverage Exclusions

From `phpunit.xml`:

```xml
<coverage>
    <exclude>
        <directory>src/Plugin</directory>
        <directory>src/DataFixtures</directory>
        <directory>src/Command</directory>
        <file>src/Kernel.php</file>
    </exclude>
</coverage>
```

**Why excluded:**
- **Plugins:** Optional modules, tested separately
- **DataFixtures:** Test data generators
- **Commands:** CLI tools, harder to test
- **Kernel:** Symfony bootstrap

---

## Test Execution

Use the `/test-unit` or `/test-functional` skills, or `Task(model: "haiku")` for custom commands. Results via `just testPrintResults` or `just testPrintResults --failures-only`.

---

## Best Practices

**Do:** AAA pattern with comments, `createStub()` for dependencies, `createMock()` only when verifying interactions, data providers for parameterized tests, one behavior per test, descriptive method names.

**Don't:** Test framework code (Doctrine/Symfony), mock what you don't own, test private methods directly, use real database in unit tests, share state between tests.

---

## CI Pipeline

1. **Unit tests** (parallel)
2. **Functional tests** (requires database)
3. **Code quality checks** (Mago linter, analyzer, guard)
4. **Coverage report** (uploaded to CI)

All must pass before merge.

---

**Related Documentation:**
- [Architecture](architecture.md) - What to test in each layer
- [Conventions](conventions.md) - Code standards for tests
