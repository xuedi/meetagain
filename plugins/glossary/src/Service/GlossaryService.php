<?php declare(strict_types=1);

namespace Plugin\Glossary\Service;

use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Enum\ItemAction;
use App\Item\ActionDispatcher;
use App\Item\AdminFilterService;
use App\Item\FilterService;
use App\Item\Tag\TagService;
use App\Service\Config\LanguageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Module\Proposal\Contract\Change\ChangeInterface;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Repository\GlossaryRepository;
use Plugin\Glossary\Review\GlossaryChangeTarget;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class GlossaryService
{
    private const int IMPORT_FLUSH_EVERY = 200;

    public function __construct(
        private EntityManagerInterface $em,
        private GlossaryRepository $repo,
        private FilterService $itemFilter,
        private AdminFilterService $adminItemFilter,
        private EntityActionDispatcher $dispatcher,
        private TagService $tagService,
        private ActionDispatcher $itemActionDispatcher,
        private ChangeInterface $changeProposalService,
        private ConfigService $configService,
        private LanguageService $languageService,
        private RequestStack $requestStack,
    ) {}

    /** @return list<int> */
    public function getTagIds(int $id): array
    {
        return $this->tagService->getTagIds(GlossaryTaggableTypeProvider::ITEM_TYPE, $id);
    }

    /** @param list<int> $tagIds */
    public function encodeTagIds(array $tagIds): ?string
    {
        sort($tagIds);

        return $tagIds === [] ? null : implode(',', $tagIds);
    }

    /** @return list<int> */
    public function decodeTagIds(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_values(array_map(intval(...), array_filter(explode(',', $value), static fn(string $id): bool => trim($id) !== '')));
    }

    public function delete(int $id): void
    {
        $item = $this->getManaged($id);
        if ($item === null) {
            return;
        }

        $this->em->remove($item);
        $this->em->flush();
        $this->dispatcher->dispatch(EntityAction::DeleteGlossary, $id);
        $this->itemActionDispatcher->dispatch(ItemAction::Deleted, GlossaryTaggableTypeProvider::ITEM_TYPE, $id);
        $this->changeProposalService->removeForTarget(GlossaryTaggableTypeProvider::ITEM_TYPE, $id);
    }

    /** @param list<int> $tagIds */
    public function update(Glossary $newGlossary, int $id, array $tagIds): void
    {
        $current = $this->getManaged($id);
        if ($current === null) {
            return;
        }

        $current->setPhrase((string) $newGlossary->getPhrase());
        $current->setSecondary($newGlossary->getSecondary());
        $this->applyDefinitions($current, $newGlossary->getSubmittedDefinitions() ?? []);

        $this->em->persist($current);
        $this->em->flush();

        $this->setTags($id, $tagIds);
    }

    public function applyChange(int $id, string $field, ?string $value): void
    {
        $item = $this->getManaged($id);
        if ($item === null) {
            throw new RuntimeException('Item not found');
        }

        $definitionLanguage = $this->definitionLanguageOf($field);
        if ($definitionLanguage !== null) {
            $item->setDefinition($definitionLanguage, $value);
            $this->em->flush();

            return;
        }

        switch ($field) {
            case GlossaryChangeTarget::FIELD_PHRASE:
                $item->setPhrase((string) $value);
                break;
            case GlossaryChangeTarget::FIELD_SECONDARY:
                $item->setSecondary($value === null || $value === '' ? null : $value);
                break;
            case GlossaryChangeTarget::FIELD_TAG:
                $this->setTags($id, $this->decodeTagIds($value));

                return;
            default:
                throw new InvalidArgumentException(sprintf('Unknown glossary field "%s"', $field));
        }

        $this->em->persist($item);
        $this->em->flush();
    }

    public function definitionLanguageOf(string $field): ?string
    {
        if (!str_starts_with($field, GlossaryChangeTarget::DEFINITION_PREFIX)) {
            return null;
        }

        $language = substr($field, strlen(GlossaryChangeTarget::DEFINITION_PREFIX));

        return preg_match('/^[a-z]{2}$/', $language) === 1 ? $language : null;
    }

    /**
     * @param list<int> $tagIds
     */
    public function create(Glossary $glossary, int $userId, array $tagIds = []): void
    {
        $this->prepareNew($glossary, $userId);

        $this->em->persist($glossary);
        $this->em->flush();

        $id = (int) $glossary->getId();
        if ($tagIds !== []) {
            $this->setTags($id, $tagIds);
        }
        $this->announce($id);
    }

    /**
     * @param list<array{0: Glossary, 1: list<int>}> $rows entry => tag ids
     */
    public function import(array $rows, int $userId): void
    {
        foreach (array_chunk($rows, self::IMPORT_FLUSH_EVERY) as $chunk) {
            foreach ($chunk as [$glossary]) {
                $this->prepareNew($glossary, $userId);
                $this->em->persist($glossary);
            }
            $this->em->flush();

            $tagIdsByEntry = [];
            foreach ($chunk as [$glossary, $tagIds]) {
                $tagIdsByEntry[(int) $glossary->getId()] = $tagIds;
            }
            $this->tagService->addTags(GlossaryTaggableTypeProvider::ITEM_TYPE, $tagIdsByEntry);

            foreach ($chunk as [$glossary]) {
                $this->announce((int) $glossary->getId());
            }
        }
    }

    /** @param list<int> $tagIds */
    public function mergeImport(Glossary $entry, ?string $secondary, string $language, ?string $definition, array $tagIds): void
    {
        if ($secondary !== null && $secondary !== '') {
            $entry->setSecondary($secondary);
        }
        if ($definition !== null && trim($definition) !== '') {
            $entry->setDefinition($language, $definition);
        }
        $this->em->flush();

        $this->tagService->addTags(GlossaryTaggableTypeProvider::ITEM_TYPE, [(int) $entry->getId() => $tagIds]);
    }

    public function get(int $id): ?Glossary
    {
        return $this->repo->findOneAllowed($id, $this->allowedIds());
    }

    public function getManaged(int $id): ?Glossary
    {
        return $this->repo->findOneAllowed($id, $this->managedIds());
    }

    /** @return Glossary[] */
    public function getList(): array
    {
        return $this->repo->findAllowed($this->allowedIds());
    }

    /** @return list<int> */
    public function getVisibleIds(): array
    {
        return $this->repo->findAllowedIds($this->allowedIds());
    }

    /**
     * @param list<int> $ids
     * @return array<int, Glossary> id => entry, visible entries only
     */
    public function getByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $allowed = $this->allowedIds();
        $wanted = $allowed === null ? $ids : array_values(array_intersect($ids, $allowed));

        $entries = [];
        foreach ($this->repo->findAllowed($wanted) as $entry) {
            $entries[(int) $entry->getId()] = $entry;
        }

        return $entries;
    }

    /** @return array<string, Glossary> normalised phrase => visible entry */
    public function phraseIndex(): array
    {
        $index = [];
        foreach ($this->getList() as $entry) {
            $index[$this->normalizePhrase((string) $entry->getPhrase())] ??= $entry;
        }

        return $index;
    }

    public function isDuplicatePhrase(string $phrase): bool
    {
        return isset($this->phraseIndex()[$this->normalizePhrase($phrase)]);
    }

    public function normalizePhrase(string $phrase): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $phrase)));
    }

    public function definitionFor(Glossary $entry, ?string $locale = null): string
    {
        return $entry->resolveDefinition($locale ?? $this->requestStack->getCurrentRequest()?->getLocale(), $this->sourceLocale());
    }

    public function sourceLocale(): string
    {
        return $this->languageService->getFilteredDefaultLocale();
    }

    public function draftOf(Glossary $entry): Glossary
    {
        return new Glossary()
            ->setPhrase((string) $entry->getPhrase())
            ->setSecondary($entry->getSecondary())
            ->setTermLanguage($entry->getTermLanguage())
            ->submitDefinitions($entry->getDefinitionMap());
    }

    private function prepareNew(Glossary $glossary, int $userId): void
    {
        $glossary->setCreatedBy($userId);
        $glossary->setCreatedAt(new DateTimeImmutable());
        if ($glossary->getTermLanguage() === null) {
            $glossary->setTermLanguage($this->configService->getConfig()->getTermLanguage());
        }
        $this->applyDefinitions($glossary, $glossary->getSubmittedDefinitions() ?? []);
    }

    private function announce(int $id): void
    {
        $this->dispatcher->dispatch(EntityAction::CreateGlossary, $id);
        $this->itemActionDispatcher->dispatch(ItemAction::Created, GlossaryTaggableTypeProvider::ITEM_TYPE, $id);
    }

    /** @param array<string, string> $definitions */
    private function applyDefinitions(Glossary $glossary, array $definitions): void
    {
        foreach ($definitions as $language => $text) {
            $glossary->setDefinition($language, $text);
        }
    }

    /** @param list<int> $tagIds */
    private function setTags(int $id, array $tagIds): void
    {
        $this->tagService->setTags(GlossaryTaggableTypeProvider::ITEM_TYPE, $id, $tagIds);
    }

    /** @return list<int>|null */
    private function allowedIds(): ?array
    {
        return $this->itemFilter->getAllowedItemIds(GlossaryTaggableTypeProvider::ITEM_TYPE);
    }

    /** @return list<int>|null */
    private function managedIds(): ?array
    {
        return $this->adminItemFilter->getAllowedItemIds(GlossaryTaggableTypeProvider::ITEM_TYPE);
    }
}
