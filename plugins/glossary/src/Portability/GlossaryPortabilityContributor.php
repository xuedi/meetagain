<?php declare(strict_types=1);

namespace Plugin\Glossary\Portability;

use App\Item\Portability\ItemImportContext;
use App\Item\Portability\ItemImportResult;
use App\Item\Portability\ItemPortabilityContributorInterface;
use App\Item\Portability\PortableImageWriterInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryCategorizableTypeProvider;
use Plugin\Glossary\Repository\GlossaryRepository;

/**
 * A glossary is a phrase dictionary, so the phrase is the key: an incoming duplicate resolves to
 * the entry already present. Pending member suggestions are workflow state and stay behind.
 */
readonly class GlossaryPortabilityContributor implements ItemPortabilityContributorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private GlossaryRepository $glossaryRepo,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'glossary';
    }

    #[Override]
    public function getItemType(): string
    {
        return GlossaryCategorizableTypeProvider::ITEM_TYPE;
    }

    #[Override]
    public function exportItems(array $itemIds, PortableImageWriterInterface $images): array
    {
        $rows = [];

        foreach ($this->glossaryRepo->findBy(['id' => $itemIds]) as $entry) {
            $rows[] = [
                'ref' => (int) $entry->getId(),
                'phrase' => $entry->getPhrase(),
                'pinyin' => $entry->getPinyin(),
                'explanation' => $entry->getExplanation(),
                'approved' => $entry->getApproved(),
            ];
        }

        return $rows;
    }

    #[Override]
    public function importItems(array $rows, ItemImportContext $context): ItemImportResult
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
            $entry->setPinyin($this->nullableString($row['pinyin'] ?? null));
            $entry->setExplanation($this->nullableString($row['explanation'] ?? null));
            $entry->setApproved((bool) ($row['approved'] ?? false));
            $entry->setCreatedBy((int) $context->getSystemUser()->getId());
            $entry->setCreatedAt(new DateTimeImmutable());

            $this->em->persist($entry);
            $refToItemId[$ref] = $entry;
            ++$created;
        }

        $this->em->flush();

        return new ItemImportResult(
            refToItemId: array_map(static fn(Glossary $entry): int => (int) $entry->getId(), $refToItemId),
            created: $created,
            matched: $matched,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
