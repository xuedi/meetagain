<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Activity\ActivityService;
use App\Activity\Messages\SendMessage;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Service\Security\ContentSanitizer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use InvalidArgumentException;

readonly class MessageService
{
    public const int MAX_LENGTH = 5000;

    public function __construct(
        private EntityManagerInterface $em,
        private MessageRepository $repo,
        private BlockingService $blockingService,
        private ContentSanitizer $contentSanitizer,
        private ActivityService $activityService,
    ) {}

    public function getMessages(User $user, ?User $partner = null): ?array
    {
        return $this->repo->getMessages($user, $partner);
    }

    public function hasNewMessages(User $user): bool
    {
        return $this->repo->hasNewMessages($user);
    }

    public function getMessageCount(User $user): int
    {
        return $this->repo->getMessageCount($user);
    }

    /**
     * @param int[] $excludeUserIds
     * @return array<int, array{messages: int, unread: int, lastMessage: DateTimeImmutable, user: User}>
     */
    public function getConversations(User $user, ?int $id = null, array $excludeUserIds = []): array
    {
        return $this->repo->getConversations($user, $id, $excludeUserIds);
    }

    /**
     * @param int[] $excludeUserIds
     * @return array<int, array{messages: int, unread: int, lastMessage: DateTimeImmutable, user: User}>
     */
    public function getConversationPage(User $user, array $excludeUserIds, int $limit, int $offset): array
    {
        return $this->repo->getConversations($user, null, $excludeUserIds, $limit, $offset);
    }

    /**
     * @param int[] $excludeUserIds
     */
    public function countConversations(User $user, array $excludeUserIds): int
    {
        return $this->repo->countConversations($user, $excludeUserIds);
    }

    /**
     * @return Message[]
     */
    public function getThreadPage(User $user, User $partner, int $limit, int $offset): array
    {
        return $this->repo->getThreadPage($user, $partner, $limit, $offset);
    }

    public function countThread(User $user, User $partner): int
    {
        return $this->repo->countThread($user, $partner);
    }

    public function findMessage(int $id): ?Message
    {
        return $this->repo->find($id);
    }

    public function send(User $from, User $to, string $content): Message
    {
        if ($from->getId() === $to->getId()) {
            throw new InvalidArgumentException('Cannot message yourself');
        }

        if ($this->blockingService->isBlocked($from, $to)) {
            throw new DomainException('blocked');
        }

        $message = new Message();
        $message->setDeleted(false);
        $message->setWasRead(false);
        $message->setSender($from);
        $message->setReceiver($to);
        $message->setCreatedAt(new DateTimeImmutable());
        $message->setContent($this->sanitize($content));

        $this->em->persist($message);
        $this->em->flush();

        $this->activityService->log(SendMessage::TYPE, $from, ['user_id' => $to->getId()]);

        return $message;
    }

    public function edit(Message $message, string $content, DateTimeImmutable $editedAt): void
    {
        $message->setContent($this->sanitize($content));
        $message->setEditedAt($editedAt);
        $this->em->flush();
    }

    public function findEditable(int $messageId, User $sender, DateTimeImmutable $now): ?Message
    {
        return $this->repo->findEditableForSender($messageId, $sender, $now);
    }

    public function markRead(User $user, User $partner): void
    {
        $this->repo->markConversationRead($user, $partner);
    }

    public function sanitize(string $content): string
    {
        return $this->contentSanitizer->toPlainText(trim($content));
    }
}
