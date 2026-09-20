<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Service;

use App\Activity\ActivityService;
use App\Item\Tag\TagService;
use App\Repository\ItemTagAssignmentRepository;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Enum\DuplicatePolicy;
use Plugin\Glossary\Service\GlossaryService;
use Plugin\Glossary\Service\ImportException;
use Plugin\Glossary\Service\TransferService;
use Plugin\Glossary\ValueObject\ImportMapping;
use ReflectionProperty;
use Symfony\Component\Filesystem\Filesystem;

class TransferServiceTest extends TestCase
{
    public function testAnAnkiExportIsReadWithItsHeaders(): void
    {
        // Arrange
        $content =
            "#separator:tab\n#html:true\n#columns:Front\tBack\tTags\n#tags column:3\n"
            . "你好\t<b>hello</b><br>hi\tHSK1 greeting\n"
            . "\"多行\"\t\"line one\nline two\"\tHSK1\n";

        // Act
        $file = $this->service()->parse($content);
        $rows = $this->service()->map($file, $this->mapping(tagsColumn: 2));

        // Assert
        self::assertSame(['Front', 'Back', 'Tags'], $file->columns);
        self::assertSame(2, $file->tagsColumn);
        self::assertCount(2, $file->rows);
        self::assertSame("hello\nhi", $rows[0]['definition']);
        self::assertSame(['HSK1', 'greeting'], $rows[0]['tags']);
        self::assertSame('多行', $rows[1]['phrase']);
        self::assertSame("line one\nline two", $rows[1]['definition']);
    }

    public function testTheDelimiterIsDetectedWhenTheFileDeclaresNone(): void
    {
        // Act
        $file = $this->service()->parse("uno;one\ndos;two\n");

        // Assert
        self::assertSame([['uno', 'one'], ['dos', 'two']], $file->rows);
    }

    public function testQuotedFieldsKeepSeparatorsAndDoubledQuotes(): void
    {
        // Act
        $file = $this->service()->parse("#separator:Comma\nword,\"one, two \"\"quoted\"\"\"\n");

        // Assert
        self::assertSame([['word', 'one, two "quoted"']], $file->rows);
    }

    public function testFileWideTagsApplyToEveryRow(): void
    {
        // Arrange
        $file = $this->service()->parse("#tags:hsk1 core\nx\ty\n");

        // Act
        $rows = $this->service()->map($file, $this->mapping());

        // Assert
        self::assertSame(['hsk1', 'core'], $rows[0]['tags']);
    }

    public function testRowsWithoutATermAreLeftOutOfTheMapping(): void
    {
        // Arrange
        $file = $this->service()->parse("a\tb\n\tonly a definition\nc\td\n");

        // Act
        $rows = $this->service()->map($file, $this->mapping(), limit: 10);

        // Assert
        self::assertSame(['a', 'c'], array_column($rows, 'phrase'));
    }

    public function testAnEmptyFileIsRefused(): void
    {
        // Assert
        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('glossary_import.error_empty');

        // Act
        $this->service()->parse("#separator:tab\n\n");
    }

    public function testAFileOverTheRowCapFailsInsteadOfTruncating(): void
    {
        // Arrange
        $content = str_repeat("word\tmeaning\n", TransferService::ROW_CAP + 1);

        // Assert
        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('glossary_import.error_too_many_rows');

        // Act
        $this->service()->parse($content);
    }

    public function testTheExportIsAnAnkiReadableTabSeparatedFile(): void
    {
        // Arrange
        $entry = new Glossary()
            ->setPhrase('你好')
            ->setSecondary('nǐ hǎo');
        new ReflectionProperty(Glossary::class, 'id')->setValue($entry, 1);

        $glossaryService = $this->createStub(GlossaryService::class);
        $glossaryService->method('definitionFor')->willReturn('hello');
        $assignments = $this->createStub(ItemTagAssignmentRepository::class);
        $assignments->method('tagIdsForItems')->willReturn([1 => [5]]);
        $tags = $this->createStub(TagService::class);
        $tags->method('getChoices')->willReturn([5 => 'Greeting words']);

        // Act
        $content = $this->service($glossaryService, $tags, $assignments)->export([$entry], 'en');

        // Assert
        self::assertStringStartsWith("#separator:tab\n#html:false\n", $content);
        self::assertStringContainsString("你好\thello\tnǐ hǎo\tGreeting_words\n", $content);
    }

    private function service(
        ?GlossaryService $glossaryService = null,
        ?TagService $tagService = null,
        ?ItemTagAssignmentRepository $assignments = null,
    ): TransferService {
        return new TransferService(
            $glossaryService ?? $this->createStub(GlossaryService::class),
            $tagService ?? $this->createStub(TagService::class),
            $assignments ?? $this->createStub(ItemTagAssignmentRepository::class),
            $this->createStub(ActivityService::class),
            new Filesystem(),
            sys_get_temp_dir(),
        );
    }

    public function testAStashedUploadIsReadBackReplacedAndSweptOnceStale(): void
    {
        // Arrange
        $dir = sys_get_temp_dir() . '/glossary-stash-' . bin2hex(random_bytes(4));
        $filesystem = new Filesystem();
        $abandoned = str_repeat('a', 32);
        $filesystem->dumpFile($dir . '/' . $abandoned . '.txt', 'abandoned');
        touch($dir . '/' . $abandoned . '.txt', time() - (2 * 86_400));
        $service = new TransferService(
            $this->createStub(GlossaryService::class),
            $this->createStub(TagService::class),
            $this->createStub(ItemTagAssignmentRepository::class),
            $this->createStub(ActivityService::class),
            $filesystem,
            $dir,
        );

        // Act
        $first = $service->stash('first', null);
        $second = $service->stash('second', $first);

        // Assert
        self::assertNull($service->stashed($first));
        self::assertSame('second', $service->stashed($second));
        self::assertNull($service->stashed($abandoned));
        self::assertNull($service->stashed('../../etc/passwd'));
        $filesystem->remove($dir);
    }

    private function mapping(?int $tagsColumn = null): ImportMapping
    {
        return new ImportMapping(
            termColumn: 0,
            definitionColumn: 1,
            secondaryColumn: null,
            tagsColumn: $tagsColumn,
            language: 'en',
            targetTagId: null,
            newTagLabel: null,
            duplicatePolicy: DuplicatePolicy::Skip,
        );
    }
}
