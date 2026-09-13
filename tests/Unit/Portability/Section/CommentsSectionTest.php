<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\CirculationCopy;
use App\Entity\CirculationHandover;
use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\Topic;
use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\CirculationSection;
use App\Portability\Section\CommentsSection;
use App\Portability\Section\TopicsSection;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class CommentsSectionTest extends SectionTestCase
{
    public function testOnlyCommentsOfConsentingMembersOnExportedTargetsTravel(): void
    {
        // Arrange
        $section = $this->section($this->comments(), topicIds: [3], handoverIds: [8]);

        // Act
        $rows = $section->export($this->scope(), $this->images());

        // Assert
        static::assertSame(
            [
                ['circulation_handover', 8, 'member@example.org'],
                ['event', 1, 'member@example.org'],
                ['photo', 7, 'member@example.org'],
                ['topic', 3, null],
            ],
            array_map(static fn(array $row): array => [$row['target_type'], $row['target_ref'], $row['email']], $rows),
        );
    }

    public function testCommentsSurviveTheRoundTrip(): void
    {
        // Arrange
        $exported = $this->section($this->comments(), topicIds: [3], handoverIds: [8])->export($this->scope(), $this->images());
        $member = new User();
        $handover = new CirculationHandover(new CirculationCopy('book', 'book', 1, new DateTimeImmutable()), null, new User(), new DateTimeImmutable());
        $context = $this->context();
        $context->mapRef(User::class, 'member@example.org', $member);
        $context->mapRef(Event::class, 1, $this->withId(new Event(), 101));
        $context->mapRef(Topic::class, 3, $this->withId(new Topic(), 103));
        $context->mapRef(CirculationHandover::class, 8, $this->withId($handover, 108));
        $context->mapItems('photo', [7 => 107]);

        // Act
        $this->section()->import($exported, $context);

        // Assert
        $comments = $this->persistedComments();
        static::assertSame(
            [['circulation_handover', 108], ['event', 101], ['photo', 107], ['topic', 103]],
            array_map(static fn(Comment $comment): array => [$comment->getTargetType(), $comment->getTargetId()], $comments),
        );
        static::assertSame($member, $comments[1]->getUser());
        static::assertSame('See you there', $comments[1]->getContent());
        static::assertSame('2030-01-06 19:00', $comments[1]->getCreatedAt()?->format('Y-m-d H:i'));
        static::assertNull($comments[3]->getUser());
        static::assertSame(4, $context->toSummary()->get('comments', Outcome::Created));
    }

    public function testACommentOnAnUnknownItemTypeIsSkippedAndOneWithoutItsTargetOrAuthorIsDropped(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(Event::class, 1, $this->withId(new Event(), 101));
        $rows = [
            ['target_type' => 'dish', 'target_ref' => 1, 'email' => null, 'content' => 'Tasty'],
            ['target_type' => 'event', 'target_ref' => 99, 'email' => null, 'content' => 'Lost'],
            ['target_type' => 'event', 'target_ref' => 1, 'email' => 'gone@example.org', 'content' => 'Gone'],
        ];

        // Act
        $this->section()->import($rows, $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame(1, $context->toSummary()->get('comments', Outcome::Skipped));
        static::assertSame(2, $context->toSummary()->get('comments', Outcome::Dropped));
    }

    private function scope(): Scope
    {
        return new Scope(
            users: [1 => 'user', 2 => 'user'],
            eventIds: [1],
            itemIds: ['photo' => [7]],
            grants: [1 => [DataCategory::Interactions], 2 => [DataCategory::Attendance]],
        );
    }

    /**
     * @return list<Comment>
     */
    private function comments(): array
    {
        $member = $this->withId(new User(), 1);
        $member->setEmail('member@example.org');
        $quiet = $this->withId(new User(), 2);
        $quiet->setEmail('quiet@example.org');

        return [
            $this->comment('event', 1, $member, 'See you there'),
            $this->comment('event', 1, $quiet, 'Me too'),
            $this->comment('topic', 3, null, 'Welcome'),
            $this->comment('topic', 4, $member, 'Not exported'),
            $this->comment('photo', 7, $member, 'Nice light'),
            $this->comment('circulation_handover', 8, $member, 'Friday works'),
        ];
    }

    private function comment(string $targetType, int $targetId, ?User $author, string $content): Comment
    {
        $comment = new Comment();
        $comment->setTargetType($targetType);
        $comment->setTargetId($targetId);
        $comment->setUser($author);
        $comment->setCreatedAt(new DateTimeImmutable('2030-01-06 19:00'));
        $comment->setContent($content);

        return $comment;
    }

    /**
     * @param list<Comment> $comments
     * @param list<int> $topicIds
     * @param list<int> $handoverIds
     */
    private function section(array $comments = [], array $topicIds = [], array $handoverIds = []): CommentsSection
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturnCallback(static fn(array $criteria): array => array_values(array_filter(
            $comments,
            static fn(Comment $comment): bool => $comment->getTargetType() === $criteria['targetType'] && in_array($comment->getTargetId(), $criteria['targetId'], true),
        )));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $topics = $this->createStub(TopicsSection::class);
        $topics->method('exportedIds')->willReturn($topicIds);
        $circulation = $this->createStub(CirculationSection::class);
        $circulation->method('exportedHandoverIds')->willReturn($handoverIds);

        return new CommentsSection($em, $topics, $circulation);
    }

    /**
     * @return list<Comment>
     */
    private function persistedComments(): array
    {
        return array_values(array_filter($this->persisted, static fn(object $entity): bool => $entity instanceof Comment));
    }
}
