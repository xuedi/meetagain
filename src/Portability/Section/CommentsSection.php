<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Circulation\Comment\HandoverTargetProvider;
use App\Comment\EventTargetProvider;
use App\Entity\CirculationHandover;
use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\Topic;
use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use App\Service\TownHall\TopicService;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class CommentsSection implements SectionInterface
{
    private const array ENTITY_TARGETS = [EventTargetProvider::TYPE, TopicService::TYPE, HandoverTargetProvider::TYPE];

    public function __construct(
        private EntityManagerInterface $em,
        private TopicsSection $topics,
        private CirculationSection $circulation,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'comments';
    }

    #[Override]
    public function getOrder(): int
    {
        return 80;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        $targets = [
            ...$scope->itemIds,
            EventTargetProvider::TYPE => $scope->eventIds,
            TopicService::TYPE => $this->topics->exportedIds($scope),
            HandoverTargetProvider::TYPE => $this->circulation->exportedHandoverIds($scope),
        ];
        ksort($targets);

        $rows = [];
        foreach ($targets as $targetType => $targetIds) {
            if ($targetIds === []) {
                continue;
            }

            $comments = $this->em->getRepository(Comment::class)->findBy(['targetType' => $targetType, 'targetId' => $targetIds], ['id' => 'ASC']);
            foreach ($comments as $comment) {
                $author = $comment->getUser();
                if ($author !== null && !$scope->grants($author, DataCategory::Interactions)) {
                    continue;
                }

                $rows[] = [
                    'target_type' => $targetType,
                    'target_ref' => $comment->getTargetId(),
                    'email' => $author?->getEmail(),
                    'created_at' => $comment->getCreatedAt()?->format(DateTimeInterface::ATOM),
                    'content' => $comment->getContent(),
                ];
            }
        }

        return $rows;
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $targetType = (string) ($row['target_type'] ?? '');
            $isItemTarget = !in_array($targetType, self::ENTITY_TARGETS, true);
            if ($isItemTarget && !$context->knowsItemType($targetType)) {
                $context->count($this->getKey(), Outcome::Skipped);
                continue;
            }

            $targetId = $this->targetId($targetType, $row['target_ref'] ?? null, $context);
            $email = $row['email'] ?? null;
            $author = $context->resolveRef(User::class, $email);

            $authorMissing = $email !== null && $author === null;
            if ($targetId === null || $authorMissing) {
                $context->count($this->getKey(), Outcome::Dropped);
                continue;
            }

            $comment = new Comment();
            $comment->setTargetType($targetType);
            $comment->setTargetId($targetId);
            $comment->setUser($author);
            $comment->setCreatedAt($this->date($row['created_at'] ?? null) ?? new DateTimeImmutable());
            $comment->setContent((string) ($row['content'] ?? ''));

            $this->em->persist($comment);
            $context->count($this->getKey(), Outcome::Created);
        }
    }

    private function targetId(string $targetType, mixed $ref, ImportContext $context): ?int
    {
        return match ($targetType) {
            EventTargetProvider::TYPE => $context->resolveRef(Event::class, $ref)?->getId(),
            TopicService::TYPE => $context->resolveRef(Topic::class, $ref)?->getId(),
            HandoverTargetProvider::TYPE => $context->resolveRef(CirculationHandover::class, $ref)?->getId(),
            default => $context->resolveItem($targetType, $ref),
        };
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== '' ? new DateTimeImmutable($value) : null;
    }
}
