<?php declare(strict_types=1);

namespace Plugin\Glossary\Service;

use App\Activity\ActivityService;
use App\Entity\ItemTag;
use App\Entity\User;
use App\Item\Tag\TagService;
use App\Repository\ItemTagAssignmentRepository;
use Plugin\Glossary\Activity\Messages\EntriesImported;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Enum\DuplicatePolicy;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\ValueObject\ImportFile;
use Plugin\Glossary\ValueObject\ImportMapping;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

readonly class TransferService
{
    public const int ROW_CAP = 10_000;
    public const int PREVIEW_ROWS = 10;
    private const int STASH_TTL_SECONDS = 86_400;

    private const array SEPARATORS = [
        'tab' => "\t",
        'comma' => ',',
        'semicolon' => ';',
        'pipe' => '|',
        'space' => ' ',
        'colon' => ':',
    ];

    public function __construct(
        private GlossaryService $glossaryService,
        private TagService $tagService,
        private ItemTagAssignmentRepository $assignmentRepo,
        private ActivityService $activityService,
        private Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/var/glossary_import')]
        private string $stashDir,
    ) {}

    public function parse(string $content): ImportFile
    {
        $lines = preg_split('/\r\n|\n|\r/', (string) preg_replace('/^\xEF\xBB\xBF/', '', $content)) ?: [];

        $headerLines = 0;
        while (isset($lines[$headerLines]) && str_starts_with($lines[$headerLines], '#')) {
            ++$headerLines;
        }
        $headers = $this->headers(array_slice($lines, 0, $headerLines));

        $body = implode("\n", array_slice($lines, $headerLines));
        $separator = $this->separator($headers['separator'] ?? null, $body);
        $rows = $this->rows($body, $separator);
        if ($rows === []) {
            throw new ImportException('glossary_import.error_empty');
        }

        $width = max(array_map(count(...), $rows));
        $tagsColumn = isset($headers['tags column']) ? (int) $headers['tags column'] - 1 : null;

        return new ImportFile(
            columns: $this->columnNames($headers['columns'] ?? '', $separator, $width),
            rows: $rows,
            tagsColumn: $tagsColumn !== null && $tagsColumn >= 0 && $tagsColumn < $width ? $tagsColumn : null,
            globalTags: $this->splitTags($headers['tags'] ?? ''),
            html: in_array(mb_strtolower($headers['html'] ?? ''), ['true', '1', 'yes'], true),
        );
    }

    /** @return list<array{phrase: string, secondary: ?string, definition: string, tags: list<string>}> */
    public function map(ImportFile $file, ImportMapping $mapping, int $limit = 0): array
    {
        $mapped = [];
        foreach ($file->rows as $row) {
            $phrase = $this->cell($file, $row, $mapping->termColumn);
            if ($phrase === '') {
                continue;
            }

            $secondary = $this->cell($file, $row, $mapping->secondaryColumn);
            $mapped[] = [
                'phrase' => $phrase,
                'secondary' => $secondary === '' ? null : $secondary,
                'definition' => $this->cell($file, $row, $mapping->definitionColumn),
                'tags' => array_values(array_unique([...$file->globalTags, ...$this->splitTags($this->cell($file, $row, $mapping->tagsColumn))])),
            ];
            if ($limit > 0 && count($mapped) >= $limit) {
                break;
            }
        }

        return $mapped;
    }

    /** @return array{created: int, updated: int, skipped: int, unknownTags: list<string>, tagId: ?int} */
    public function execute(ImportFile $file, ImportMapping $mapping, User $user): array
    {
        $labels = $this->labelIndex();
        $target = $this->targetTag($mapping, $labels);
        $index = $this->glossaryService->phraseIndex();

        $newRows = [];
        $updated = 0;
        $skipped = 0;
        $unknown = [];
        foreach ($this->map($file, $mapping) as $row) {
            if ($row['definition'] === '') {
                ++$skipped;
                continue;
            }

            $tagIds = $target === null ? [] : [(int) $target->getId()];
            foreach ($row['tags'] as $label) {
                $tagId = $this->matchTag($label, $labels);
                if ($tagId === null) {
                    $unknown[$label] = true;
                    continue;
                }
                $tagIds[] = $tagId;
            }
            $tagIds = array_values(array_unique($tagIds));

            $key = $this->glossaryService->normalizePhrase($row['phrase']);
            $existing = $index[$key] ?? null;
            if ($existing !== null && $mapping->duplicatePolicy !== DuplicatePolicy::Create) {
                $managed = $existing->getId() === null ? null : $this->glossaryService->getManaged((int) $existing->getId());
                if ($mapping->duplicatePolicy === DuplicatePolicy::Skip || $managed === null) {
                    ++$skipped;
                    continue;
                }

                $this->glossaryService->mergeImport($managed, $row['secondary'], $mapping->language, $row['definition'], $tagIds);
                ++$updated;
                continue;
            }

            $entry = new Glossary()
                ->setPhrase($row['phrase'])
                ->setSecondary($row['secondary']);
            $entry->setDefinition($mapping->language, $row['definition']);
            $newRows[] = [$entry, $tagIds];
            $index[$key] = $entry;
        }

        $this->glossaryService->import($newRows, (int) $user->getId());

        $summary = [
            'created' => count($newRows),
            'updated' => $updated,
            'skipped' => $skipped,
            'unknownTags' => array_map(strval(...), array_keys($unknown)),
            'tagId' => $target?->getId(),
        ];
        $this->activityService->log(EntriesImported::TYPE, $user, [
            'created' => $summary['created'],
            'updated' => $summary['updated'],
            'skipped' => $summary['skipped'],
        ]);

        return $summary;
    }

    /** @param Glossary[] $entries */
    public function export(array $entries, ?string $locale): string
    {
        $ids = array_map(static fn(Glossary $entry): int => (int) $entry->getId(), $entries);
        $tagIdsByEntry = $this->assignmentRepo->tagIdsForItems(GlossaryTaggableTypeProvider::ITEM_TYPE, array_values($ids));
        $labels = $this->tagService->getChoices(GlossaryTaggableTypeProvider::ITEM_TYPE, $locale);

        $lines = ["#separator:tab\n#html:false\n#columns:Front\tBack\tSecondary\tTags\n#tags column:4\n"];
        foreach ($entries as $entry) {
            $tags = [];
            foreach ($tagIdsByEntry[(int) $entry->getId()] ?? [] as $tagId) {
                if (!isset($labels[$tagId])) {
                    continue;
                }
                $tags[] = str_replace(' ', '_', trim($labels[$tagId]));
            }

            $fields = [
                (string) $entry->getPhrase(),
                $this->glossaryService->definitionFor($entry, $locale),
                (string) $entry->getSecondary(),
                implode(' ', $tags),
            ];
            $lines[] = implode("\t", array_map($this->tsvField(...), $fields)) . "\n";
        }

        return implode('', $lines);
    }

    public function stash(string $content, ?string $previousStashId): string
    {
        $this->discard($previousStashId);
        $this->sweepStale();

        $stashId = bin2hex(random_bytes(16));
        $this->filesystem->dumpFile($this->stashDir . '/' . $stashId . '.txt', $content);

        return $stashId;
    }

    public function stashed(?string $stashId): ?string
    {
        $path = $this->stashPath($stashId);

        return $path !== null && is_file($path) ? (string) file_get_contents($path) : null;
    }

    public function discard(?string $stashId): void
    {
        $path = $this->stashPath($stashId);
        if ($path !== null) {
            $this->filesystem->remove($path);
        }
    }

    private function stashPath(?string $stashId): ?string
    {
        return preg_match('/^[a-f0-9]{32}$/', (string) $stashId) === 1 ? $this->stashDir . '/' . $stashId . '.txt' : null;
    }

    private function sweepStale(): void
    {
        $cutoff = time() - self::STASH_TTL_SECONDS;
        foreach (glob($this->stashDir . '/*.txt') ?: [] as $file) {
            $modified = filemtime($file);
            if ($modified !== false && $modified < $cutoff) {
                $this->filesystem->remove($file);
            }
        }
    }

    private function tsvField(string $value): string
    {
        $needsQuotes = strpbrk($value, "\t\n\r\"") !== false;

        return $needsQuotes ? '"' . str_replace('"', '""', $value) . '"' : $value;
    }

    /**
     * @param list<string> $lines
     * @return array<string, string> lower-cased header name => value
     */
    private function headers(array $lines): array
    {
        $headers = [];
        foreach ($lines as $line) {
            $parts = explode(':', substr($line, 1), 2);
            if (count($parts) !== 2) {
                continue;
            }
            $headers[mb_strtolower(trim($parts[0]))] = trim($parts[1]);
        }

        return $headers;
    }

    private function separator(?string $declared, string $body): string
    {
        $declared = trim((string) $declared);
        if ($declared !== '') {
            return self::SEPARATORS[mb_strtolower($declared)] ?? mb_substr($declared, 0, 1);
        }

        $sample = implode("\n", array_slice(preg_split('/\n/', $body) ?: [], 0, 20));
        if (str_contains($sample, "\t")) {
            return "\t";
        }

        $counts = ['semicolon' => substr_count($sample, ';'), 'comma' => substr_count($sample, ','), 'pipe' => substr_count($sample, '|')];
        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] > 0 ? self::SEPARATORS[$best] : ',';
    }

    /** @return list<list<string>> */
    private function rows(string $body, string $separator): array
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return [];
        }
        fwrite($handle, $body);
        rewind($handle);

        $rows = [];
        while (($fields = fgetcsv($handle, null, $separator, '"', '')) !== false) {
            $fields = array_map(static fn(?string $field): string => trim((string) $field), $fields);
            if (implode('', $fields) === '') {
                continue;
            }

            $rows[] = $fields;
            if (count($rows) > self::ROW_CAP) {
                fclose($handle);

                throw new ImportException('glossary_import.error_too_many_rows', ['%cap%' => self::ROW_CAP]);
            }
        }
        fclose($handle);

        return $rows;
    }

    /** @return list<string> */
    private function columnNames(string $declared, string $separator, int $width): array
    {
        $names = $declared === '' ? [] : array_map(trim(...), explode($separator, $declared));

        $columns = [];
        for ($i = 0; $i < $width; ++$i) {
            $columns[] = $names[$i] ?? '';
        }

        return $columns;
    }

    /** @param list<string> $row */
    private function cell(ImportFile $file, array $row, ?int $column): string
    {
        if ($column === null) {
            return '';
        }

        $value = $row[$column] ?? '';
        if ($file->html) {
            $value = html_entity_decode(strip_tags((string) preg_replace('~<br\s*/?>~i', "\n", $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return trim($value);
    }

    /** @return list<string> */
    private function splitTags(string $value): array
    {
        return array_values(array_filter(preg_split('/\s+/u', trim($value)) ?: [], static fn(string $tag): bool => $tag !== ''));
    }

    /** @return array<string, int> lower-cased label in any locale => assignable tag id */
    private function labelIndex(): array
    {
        $index = [];
        foreach ($this->tagService->getVocabulary(GlossaryTaggableTypeProvider::ITEM_TYPE) as $tag) {
            if ($tag->isManaged()) {
                continue;
            }
            foreach ($tag->getLabels() as $label) {
                $index[mb_strtolower(trim($label))] ??= (int) $tag->getId();
            }
        }

        return $index;
    }

    /** @param array<string, int> $labels */
    private function matchTag(string $raw, array $labels): ?int
    {
        $label = mb_strtolower(str_replace('_', ' ', $raw));
        $segments = explode('::', $label);

        return $labels[$label] ?? $labels[end($segments)] ?? null;
    }

    /** @param array<string, int> $labels */
    private function targetTag(ImportMapping $mapping, array $labels): ?ItemTag
    {
        $newLabel = trim((string) $mapping->newTagLabel);
        $existingId = $newLabel === '' ? $mapping->targetTagId : $labels[mb_strtolower($newLabel)] ?? null;

        if ($existingId !== null) {
            $tag = $this->tagService->getManagedTag(GlossaryTaggableTypeProvider::ITEM_TYPE, $existingId);
            if ($tag === null || $tag->isManaged()) {
                throw new ImportException('glossary_import.error_unknown_tag');
            }

            return $tag;
        }

        if ($newLabel === '') {
            return null;
        }

        return $this->tagService->addTag(GlossaryTaggableTypeProvider::ITEM_TYPE, [$this->glossaryService->sourceLocale() => $newLabel], null);
    }
}
