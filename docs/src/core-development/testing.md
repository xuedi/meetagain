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
├── phpunit.xml                   ← PHPUnit configuration
├── bootstrap.php                 ← test bootstrap
└── reports/
    ├── clover.xml                ← coverage report
    └── junit.xml                 ← JUnit results
```

`tests/Unit/` mirrors `src/` — `src/Service/System/HealthCheckService.php` is tested at
`tests/Unit/Service/System/HealthCheckServiceTest.php`.

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

## Functional and smoke tests

This repository ships unit tests only. The functional and smoke suites need a populated database and
run in the maintainers' own pipeline outside this repository.

What CI checks here beyond the unit suite: the static analysis, and every demo archive imported with
`--strict` into a fresh database built by the real migrations, exported again and compared row count
by row count - so an import or export change that loses data fails the build. To try a change by hand,
build an instance with `just devModeImport <archive>` (see [Demo Data](demo-data.md)).

---

## Running tests

```bash
just testUnit                              # All unit tests
just testUnit tests/Unit/Service/          # Specific directory
just testUnit tests/Unit/Service/CleanupServiceTest.php  # Single file
just test                                  # Unit tests + quality checks
just testCoverage                          # HTML coverage report
just testPrintResults                      # Machine-readable summary
just testPrintResults --failures-only      # Failures only
```
