<?php declare(strict_types=1);

namespace Tests\Unit\Moderation\Subject;

use App\Comment\CommentService;
use App\Comment\TargetProviderInterface;
use App\Comment\TargetRegistry;
use App\Entity\Comment;
use App\Moderation\Subject\CommentSubject;
use App\Repository\CommentRepository;
use App\Service\Security\ContentSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Tests\Unit\Stubs\UserStub;

class CommentSubjectTest extends TestCase
{
    public function testDescribeSnapshotsTargetAndContent(): void
    {
        // Arrange
        $author = new UserStub()->setId(2);
        $subject = $this->makeSubject($this->makeComment($author), '/events/7');

        // Act
        $snapshot = $subject->describe(11, new UserStub()->setId(1));

        // Assert
        static::assertNotNull($snapshot);
        static::assertSame('event #7', $snapshot->label);
        static::assertSame('Buy cheap watches', $snapshot->excerpt);
        static::assertSame($author, $snapshot->author);
    }

    #[DataProvider('provideRefusedCases')]
    public function testDescribeRefuses(bool $commentExists, int $authorId, ?string $targetUrl): void
    {
        // Arrange
        $comment = $commentExists ? $this->makeComment(new UserStub()->setId($authorId)) : null;
        $subject = $this->makeSubject($comment, $targetUrl);

        // Act
        $snapshot = $subject->describe(11, new UserStub()->setId(1));

        // Assert
        static::assertNull($snapshot);
    }

    public static function provideRefusedCases(): iterable
    {
        yield 'missing comment' => [false, 2, '/events/7'];
        yield 'your own comment' => [true, 1, '/events/7'];
        yield 'comment on a target that is gone' => [true, 2, null];
    }

    public function testRemoveDeletesTheComment(): void
    {
        // Arrange
        $comment = $this->makeComment(new UserStub()->setId(2));
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($comment);
        $subject = $this->makeSubject($comment, '/events/7', $em);

        // Act
        $subject->remove(11);
    }

    public function testRemoveToleratesAMissingComment(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('remove');
        $subject = $this->makeSubject(null, '/events/7', $em);

        // Act
        $subject->remove(11);
    }

    private function makeComment(UserStub $author): Comment
    {
        return new Comment()
            ->setTargetType('event')
            ->setTargetId(7)
            ->setUser($author)
            ->setContent('Buy cheap watches');
    }

    private function makeSubject(?Comment $comment, ?string $targetUrl, ?EntityManagerInterface $em = null): CommentSubject
    {
        $repository = $this->createStub(CommentRepository::class);
        $repository->method('find')->willReturn($comment);
        $target = $this->createStub(TargetProviderInterface::class);
        $target->method('getTypeKey')->willReturn('event');
        $target->method('getReturnUrl')->willReturn($targetUrl);
        $registry = new TargetRegistry([$target]);
        $config = new HtmlSanitizerConfig()->allowSafeElements();
        $sanitizer = new ContentSanitizer(new HtmlSanitizer($config), new HtmlSanitizer($config));

        return new CommentSubject(new CommentService($repository, $em ?? $this->createStub(EntityManagerInterface::class), $sanitizer, $registry), $registry);
    }
}
