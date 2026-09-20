<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification\User;

use App\Circulation\Comment\HandoverTargetProvider;
use App\Entity\CirculationCopy;
use App\Entity\CirculationHandover;
use App\Entity\Comment;
use App\Entity\User;
use App\Repository\CirculationCopyRepository;
use App\Repository\CirculationHandoverRepository;
use App\Repository\CirculationRequestRepository;
use App\Repository\CommentRepository;
use App\Service\Notification\User\CirculationNotificationProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Contracts\Translation\TranslatorInterface;

class CirculationNotificationProviderKeyTest extends TestCase
{
    public function testTheTwoItemsOnTheHandoverRouteCarryDifferentKeys(): void
    {
        // Arrange
        $receiver = $this->user(1);
        $sender = $this->user(2);
        $handover = $this->handover($receiver, $sender);

        $provider = new CirculationNotificationProvider(
            $this->handovers($handover, $receiver),
            $this->noCopies(),
            $this->createStub(CirculationRequestRepository::class),
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

        $provider = new CirculationNotificationProvider(
            $this->handovers($handover, $sender),
            $this->noCopies(),
            $this->createStub(CirculationRequestRepository::class),
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

    private function handover(User $receiver, User $sender): CirculationHandover
    {
        $copy = new CirculationCopy('books', 'book', 42, new DateTimeImmutable('-1 month'));
        $handover = new CirculationHandover($copy, $sender, $receiver, new DateTimeImmutable('-1 day'));
        new ReflectionProperty(CirculationHandover::class, 'id')->setValue($handover, 7);

        return $handover;
    }

    private function handovers(CirculationHandover $handover, User $for): CirculationHandoverRepository
    {
        $repo = $this->createStub(CirculationHandoverRepository::class);
        $repo->method('findOpenForUser')->willReturnCallback(static fn(User $user): array => $user->getId() === $for->getId() ? [$handover] : []);

        return $repo;
    }

    private function noCopies(): CirculationCopyRepository
    {
        $repo = $this->createStub(CirculationCopyRepository::class);
        $repo->method('findHeldBy')->willReturn([]);

        return $repo;
    }

    private function unreadChatFrom(User $author, CirculationHandover $handover): CommentRepository
    {
        $comment = new Comment();
        $comment->setUser($author);
        $comment->setCreatedAt(new DateTimeImmutable('-1 hour'));
        $comment->setTargetType(HandoverTargetProvider::TYPE);
        $comment->setTargetId((int) $handover->getId());

        $repo = $this->createStub(CommentRepository::class);
        $repo->method('findForTarget')->willReturn([$comment]);

        return $repo;
    }

    private function noChat(): CommentRepository
    {
        $repo = $this->createStub(CommentRepository::class);
        $repo->method('findForTarget')->willReturn([]);

        return $repo;
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
