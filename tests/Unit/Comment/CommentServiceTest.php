<?php declare(strict_types=1);

namespace Tests\Unit\Comment;

use App\Comment\CommentService;
use App\Comment\InvalidContentException;
use App\Comment\TargetProviderInterface;
use App\Comment\TargetRegistry;
use App\Entity\Comment;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\CommentRepository;
use App\Service\Security\ContentSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class CommentServiceTest extends TestCase
{
    public function testCreateStripsMarkupAndPersists(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');
        $service = $this->makeService($em);

        // Act
        $comment = $service->create('event', 42, $this->makeUser(1), '  <script>alert(1)</script>Nice <b>meetup</b>  ');

        // Assert
        self::assertSame('event', $comment->getTargetType());
        self::assertSame(42, $comment->getTargetId());
        self::assertSame('Nice meetup', $comment->getContent());
        self::assertNotNull($comment->getCreatedAt());
    }

    public static function rejectedContentProvider(): iterable
    {
        yield 'blank input' => ['   ', InvalidContentException::REASON_EMPTY];
        yield 'markup with no text' => ['<script>alert(1)</script>', InvalidContentException::REASON_EMPTY];
        yield 'over the length cap' => [str_repeat('x', Comment::MAX_CONTENT_LENGTH + 1), InvalidContentException::REASON_TOO_LONG];
    }

    #[DataProvider('rejectedContentProvider')]
    public function testCreateRejectsInvalidContent(string $content, string $expectedReason): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $service = $this->makeService($em);

        // Act + Assert
        try {
            $service->create('event', 42, $this->makeUser(1), $content);
            self::fail('Expected InvalidContentException.');
        } catch (InvalidContentException $exception) {
            self::assertSame($expectedReason, $exception->reason);
        }
    }

    public function testCreateFiresProviderHook(): void
    {
        // Arrange
        $provider = $this->createMock(TargetProviderInterface::class);
        $provider->method('getTypeKey')->willReturn('event');
        $provider->expects(self::once())->method('onCommentCreated');
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $provider);

        // Act
        $service->create('event', 42, $this->makeUser(1), 'Hello');
    }

    public function testCreateWithoutProviderSkipsHook(): void
    {
        // Arrange
        $provider = $this->createMock(TargetProviderInterface::class);
        $provider->method('getTypeKey')->willReturn('event');
        $provider->expects(self::never())->method('onCommentCreated');
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $provider);

        // Act
        $service->create('photo', 42, $this->makeUser(1), 'Hello');
    }

    public static function deletePermissionProvider(): iterable
    {
        yield 'owner may delete' => [1, UserRole::User, true];
        yield 'stranger may not delete' => [2, UserRole::User, false];
        yield 'admin may delete anything' => [2, UserRole::Admin, true];
    }

    #[DataProvider('deletePermissionProvider')]
    public function testCanDelete(int $actorId, UserRole $role, bool $expected): void
    {
        // Arrange
        $service = $this->makeService($this->createStub(EntityManagerInterface::class));
        $comment = new Comment();
        $comment->setUser($this->makeUser(1));
        $actor = $this->makeUser($actorId);
        $actor->setRole($role);

        // Act
        $result = $service->canDelete($comment, $actor);

        // Assert
        self::assertSame($expected, $result);
    }

    public function testCanDeleteRefusesStrangerOnAuthorlessComment(): void
    {
        // Arrange
        $service = $this->makeService($this->createStub(EntityManagerInterface::class));

        // Act
        $result = $service->canDelete(new Comment(), $this->makeUser(1));

        // Assert
        self::assertFalse($result);
    }

    public function testDeleteAllForRemovesEveryCommentOnTheTarget(): void
    {
        // Arrange
        $repository = $this->createStub(CommentRepository::class);
        $repository->method('findForTarget')->willReturn([new Comment(), new Comment()]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('remove');
        $em->expects(self::once())->method('flush');
        $service = $this->makeService($em, repository: $repository);

        // Act
        $service->deleteAllFor('event', 42);
    }

    private function makeService(
        EntityManagerInterface $em,
        ?TargetProviderInterface $provider = null,
        ?CommentRepository $repository = null,
    ): CommentService {
        $config = new HtmlSanitizerConfig()->allowSafeElements();

        return new CommentService(
            $repository ?? $this->createStub(CommentRepository::class),
            $em,
            new ContentSanitizer(new HtmlSanitizer($config), new HtmlSanitizer($config)),
            new TargetRegistry($provider === null ? [] : [$provider]),
        );
    }

    private function makeUser(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
