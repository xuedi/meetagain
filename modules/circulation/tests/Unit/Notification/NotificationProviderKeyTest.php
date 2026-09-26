<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Unit\Notification;

use App\Comment\CommentService;
use App\Comment\TargetRegistry;
use App\Entity\Comment;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Service\Security\ContentSanitizer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Internal\Comment\HandoverTargetProvider;
use Module\Circulation\Internal\Entity\Copy;
use Module\Circulation\Internal\Entity\Handover;
use Module\Circulation\Internal\Notification\NotificationProvider;
use Module\Circulation\Internal\Repository\CopyRepository;
use Module\Circulation\Internal\Repository\HandoverRepository;
use Module\Circulation\Internal\Repository\RequestRepository;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotificationProviderKeyTest extends TestCase
{
    public function testTheTwoItemsOnTheHandoverRouteCarryDifferentKeys(): void
    {
        // Arrange
        $receiver = $this->user(1);
        $sender = $this->user(2);
        $handover = $this->handover($receiver, $sender);

        $provider = new NotificationProvider(
            $this->handovers($handover, $receiver),
            $this->noCopies(),
            $this->createStub(RequestRepository::class),
            $this->unreadChatFrom($sender, $handover),
            $this->translator(),
        );

        // Act
        $items = $provider->getNotifications($receiver);

        // Assert
        static::assertCount(2, $items);
        static::assertSame('app_circulation_handover', $items[0]->route);
        static::assertSame('app_circulation_handover', $items[1]->route);
        static::assertSame('circulation_ready_for_you', $items[0]->key());
        static::assertSame('circulation_new_message', $items[1]->key());
    }

    public function testTheConfirmSideOfAHandoverCarriesItsOwnKey(): void
    {
        // Arrange
        $receiver = $this->user(1);
        $sender = $this->user(2);
        $handover = $this->handover($receiver, $sender);

        $provider = new NotificationProvider(
            $this->handovers($handover, $sender),
            $this->noCopies(),
            $this->createStub(RequestRepository::class),
            $this->noChat(),
            $this->translator(),
        );

        // Act
        $items = $provider->getNotifications($sender);

        // Assert
        static::assertCount(1, $items);
        static::assertSame('circulation_confirm_handover', $items[0]->key());
    }

    private function user(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }

    private function handover(User $receiver, User $sender): Handover
    {
        $copy = new Copy('books', 'book', 42, new DateTimeImmutable('-1 month'));
        $handover = new Handover($copy, $sender, $receiver, new DateTimeImmutable('-1 day'));
        new ReflectionProperty(Handover::class, 'id')->setValue($handover, 7);

        return $handover;
    }

    private function handovers(Handover $handover, User $for): HandoverRepository
    {
        $repo = $this->createStub(HandoverRepository::class);
        $repo->method('findOpenForUser')->willReturnCallback(static fn(User $user): array => $user->getId() === $for->getId() ? [$handover] : []);

        return $repo;
    }

    private function noCopies(): CopyRepository
    {
        $repo = $this->createStub(CopyRepository::class);
        $repo->method('findHeldBy')->willReturn([]);

        return $repo;
    }

    private function unreadChatFrom(User $author, Handover $handover): CommentService
    {
        $comment = new Comment();
        $comment->setUser($author);
        $comment->setCreatedAt(new DateTimeImmutable('-1 hour'));
        $comment->setTargetType(HandoverTargetProvider::TYPE);
        $comment->setTargetId((int) $handover->getId());

        $repo = $this->createStub(CommentRepository::class);
        $repo->method('findForTarget')->willReturn([$comment]);

        return $this->comments($repo);
    }

    private function noChat(): CommentService
    {
        $repo = $this->createStub(CommentRepository::class);
        $repo->method('findForTarget')->willReturn([]);

        return $this->comments($repo);
    }

    private function comments(CommentRepository $repo): CommentService
    {
        $sanitizer = new HtmlSanitizer(new HtmlSanitizerConfig());

        return new CommentService(
            $repo,
            $this->createStub(EntityManagerInterface::class),
            new ContentSanitizer($sanitizer, $sanitizer),
            new TargetRegistry([]),
        );
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
