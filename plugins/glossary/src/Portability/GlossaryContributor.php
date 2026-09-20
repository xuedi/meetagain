<?php declare(strict_types=1);

namespace Plugin\Glossary\Portability;

use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Item\ContributorInterface;
use App\Portability\Item\ImportResult;
use App\Service\Config\LanguageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Repository\GlossaryRepository;

readonly class GlossaryContributor implements ContributorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private GlossaryRepository $glossaryRepo,
        private LanguageService $languageService,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'glossary';
    }

    #[Override]
    public function getItemType(): string
    {
        return GlossaryTaggableTypeProvider::ITEM_TYPE;
    }

    #[Override]
    public function allItemIds(): array
    {
        return array_map(
            intval(...),
            $this->glossaryRepo
                ->createQueryBuilder('g')
                ->select('g.id')
                ->orderBy('g.id')
                ->getQuery()
                ->getSingleColumnResult(),
        );
    }

    #[Override]
    public function exportItems(array $itemIds, ImageWriterInterface $images): array
    {
        $rows = [];

        foreach ($this->glossaryRepo->findBy(['id' => $itemIds]) as $entry) {
            $rows[] = [
                'ref' => (int) $entry->getId(),
                'phrase' => $entry->getPhrase(),
                'secondary' => $entry->getSecondary(),
                'term_language' => $entry->getTermLanguage(),
                'definitions' => $entry->getDefinitionMap(),
            ];
        }

        return $rows;
    }

    #[Override]
    public function importItems(array $rows, ImportContext $context): ImportResult
    {
        $refToItemId = [];
        $created = 0;
        $matched = 0;

        foreach ($rows as $row) {
            $ref = (int) ($row['ref'] ?? 0);
            $phrase = (string) ($row['phrase'] ?? '');
            if ($phrase === '') {
                continue;
            }

            $existing = $this->glossaryRepo->findOneBy(['phrase' => $phrase]);
            if ($existing instanceof Glossary) {
                $refToItemId[$ref] = $existing;
                ++$matched;
                continue;
            }

            $entry = new Glossary();
            $entry->setPhrase($phrase);
            $entry->setSecondary($this->nullableString($row['secondary'] ?? $row['pinyin'] ?? null));
            $entry->setTermLanguage($this->nullableString($row['term_language'] ?? null));
            foreach ($this->definitionsOf($row) as $language => $text) {
                $entry->setDefinition($language, $text);
            }
            $entry->setCreatedBy((int) $context->getSystemUser()->getId());
            $entry->setCreatedAt(new DateTimeImmutable());

            $this->em->persist($entry);
            $refToItemId[$ref] = $entry;
            ++$created;
        }

        $this->em->flush();

        return new ImportResult(
            refToItemId: array_map(static fn(Glossary $entry): int => (int) $entry->getId(), $refToItemId),
            created: $created,
            matched: $matched,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function definitionsOf(array $row): array
    {
        if (is_array($row['definitions'] ?? null)) {
            $definitions = [];
            foreach ($row['definitions'] as $language => $text) {
                $definitions[(string) $language] = (string) $text;
            }

            return $definitions;
        }

        $legacy = $this->nullableString($row['explanation'] ?? null);

        return $legacy === null ? [] : [$this->languageService->getFilteredDefaultLocale() => $legacy];
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
