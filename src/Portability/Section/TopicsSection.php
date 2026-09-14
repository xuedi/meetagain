<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\Topic;
use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class TopicsSection implements SectionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'topics';
    }

    #[Override]
    public function getOrder(): int
    {
        return 70;
    }

    /**
     * @return list<int>
     */
    public function exportedIds(Scope $scope): array
    {
        return array_map(static fn(Topic $topic): int => (int) $topic->getId(), $this->keptTopics($scope));
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        $rows = [];
        foreach ($this->keptTopics($scope) as $topic) {
            $rows[] = [
                'ref' => $topic->getId(),
                'parent_ref' => $topic->getParent()?->getId(),
                'title' => $topic->getTitle(),
                'author_email' => $topic->getAuthor()?->getEmail(),
                'created_at' => $topic->getCreatedAt()?->format(DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        $topicRows = array_values(array_filter($rows, is_array(...)));
        usort($topicRows, static fn(array $a, array $b): int => (int) ($a['ref'] ?? 0) <=> (int) ($b['ref'] ?? 0));

        foreach ($topicRows as $row) {
            $parentRef = $row['parent_ref'] ?? null;
            $parent = $context->resolveRef(Topic::class, $parentRef);
            $authorEmail = $row['author_email'] ?? null;
            $author = $context->resolveRef(User::class, $authorEmail);

            $parentDropped = $parentRef !== null && $parent === null;
            $authorMissing = $authorEmail !== null && $author === null;
            if ($parentDropped || $authorMissing) {
                $context->count($this->getKey(), Outcome::Dropped);
                continue;
            }

            $topic = new Topic();
            $topic->setTitle((string) ($row['title'] ?? ''));
            $topic->setParent($parent);
            $topic->setAuthor($author);
            $createdAt = $this->date($row['created_at'] ?? null);
            if ($createdAt !== null) {
                $topic->setCreatedAt($createdAt);
            }

            $this->em->persist($topic);
            $context->mapRef(Topic::class, (int) ($row['ref'] ?? 0), $topic);
            $context->count($this->getKey(), Outcome::Created);
        }
    }

    /**
     * @return list<Topic>
     */
    private function keptTopics(Scope $scope): array
    {
        if ($scope->topicIds === []) {
            return [];
        }

        $topics = [];
        foreach ($this->em->getRepository(Topic::class)->findBy(['id' => $scope->topicIds], ['id' => 'ASC']) as $topic) {
            $topics[(int) $topic->getId()] = $topic;
        }

        return array_values(array_filter($topics, fn(Topic $topic): bool => $this->isKept($topic, $topics, $scope)));
    }

    /**
     * @param array<int, Topic> $topics
     */
    private function isKept(Topic $topic, array $topics, Scope $scope): bool
    {
        $author = $topic->getAuthor();
        if ($author !== null && !$scope->grants($author, DataCategory::Interactions)) {
            return false;
        }

        $parent = $topic->getParent();
        if ($parent === null) {
            return true;
        }

        $scopedParent = $topics[(int) $parent->getId()] ?? null;

        return $scopedParent !== null && $this->isKept($scopedParent, $topics, $scope);
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== '' ? new DateTimeImmutable($value) : null;
    }
}
