<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\Topic;
use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\TopicsSection;
use DateTimeImmutable;

final class TopicsSectionTest extends SectionTestCase
{
    public function testATopicTreeSurvivesTheRoundTrip(): void
    {
        // Arrange
        $member = $this->user(1, 'member@example.org');
        $root = $this->topic(1, 'Openings', $member, null);
        $child = $this->topic(2, 'Joseki', null, $root);
        $scope = new Scope(users: [1 => 'user'], grants: [1 => [DataCategory::Interactions]], topicIds: [1, 2]);
        $exported = $this->section([$root, $child])->export($scope, $this->images());

        $context = $this->context();
        $importedMember = new User();
        $context->mapRef(User::class, 'member@example.org', $importedMember);

        // Act
        $this->section()->import($exported, $context);

        // Assert
        $importedRoot = $context->resolveRef(Topic::class, 1);
        $importedChild = $context->resolveRef(Topic::class, 2);
        static::assertCount(2, $this->persisted);
        static::assertSame('Openings', $importedRoot?->getTitle());
        static::assertSame($importedMember, $importedRoot?->getAuthor());
        static::assertSame('2030-01-06 19:00', $importedRoot?->getCreatedAt()?->format('Y-m-d H:i'));
        static::assertSame($importedRoot, $importedChild?->getParent());
        static::assertNull($importedChild?->getAuthor());
        static::assertSame(2, $context->toSummary()->get('topics', Outcome::Created));
    }

    public function testATopicOfAMemberWithoutInteractionsLeavesWithItsWholeSubtree(): void
    {
        // Arrange
        $member = $this->user(1, 'member@example.org');
        $quiet = $this->user(2, 'quiet@example.org');
        $root = $this->topic(1, 'Openings', $member, null);
        $quietTopic = $this->topic(3, 'My games', $quiet, $root);
        $reply = $this->topic(4, 'Game one', $member, $quietTopic);
        $scope = new Scope(
            users: [1 => 'user', 2 => 'user'],
            grants: [1 => [DataCategory::Interactions], 2 => [DataCategory::Attendance]],
            topicIds: [1, 3, 4],
        );
        $section = $this->section([$root, $quietTopic, $reply]);

        // Act
        $rows = $section->export($scope, $this->images());

        // Assert
        static::assertSame([1], $section->exportedIds($scope));
        static::assertSame([1], array_column($rows, 'ref'));
    }

    public function testATopicWhoseAuthorOrParentDidNotArriveIsDropped(): void
    {
        // Arrange
        $context = $this->context();
        $rows = [
            ['ref' => 5, 'parent_ref' => null, 'title' => 'Openings', 'author_email' => 'gone@example.org'],
            ['ref' => 6, 'parent_ref' => 5, 'title' => 'Joseki', 'author_email' => null],
        ];

        // Act
        $this->section()->import($rows, $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame(2, $context->toSummary()->get('topics', Outcome::Dropped));
    }

    public function testTwinTopicsMatchTwoStoredTopicsInsteadOfOne(): void
    {
        // Arrange
        $member = $this->user(1, 'member@example.org');
        $first = $this->topic(5, 'off topic', $member, null);
        $second = $this->topic(8, 'off topic', $member, null);
        $context = $this->context();
        $context->mapRef(User::class, 'member@example.org', $member);
        $row = ['parent_ref' => null, 'title' => 'off topic', 'author_email' => 'member@example.org', 'created_at' => '2030-01-06T19:00:00+00:00'];

        // Act
        $this->section([$first, $second])->import([['ref' => 5, ...$row], ['ref' => 8, ...$row]], $context);

        // Assert
        static::assertSame($first, $context->resolveRef(Topic::class, 5));
        static::assertSame($second, $context->resolveRef(Topic::class, 8));
        static::assertSame(2, $context->toSummary()->get('topics', Outcome::Matched));
    }

    /**
     * @param list<Topic> $topics
     */
    private function section(array $topics = []): TopicsSection
    {
        return new TopicsSection($this->entityManager($topics));
    }

    private function user(int $id, string $email): User
    {
        $user = $this->withId(new User(), $id);
        $user->setEmail($email);

        return $user;
    }

    private function topic(int $id, string $title, ?User $author, ?Topic $parent): Topic
    {
        $topic = $this->withId(new Topic(), $id);
        $topic->setTitle($title);
        $topic->setAuthor($author);
        $topic->setParent($parent);
        $topic->setCreatedAt(new DateTimeImmutable('2030-01-06 19:00'));

        return $topic;
    }
}
