<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Message;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @param int[] $excludeUserIds User IDs to exclude from conversation list (e.g., blocked users)
     */
    public function getConversations(User $user, ?int $id = null, array $excludeUserIds = []): array
    {
        $messages = $this
            ->createQueryBuilder('m')
            ->leftJoin('m.sender', 's')
            ->addSelect('s')
            ->leftJoin('s.image', 'si')
            ->addSelect('si')
            ->leftJoin('m.receiver', 'r')
            ->addSelect('r')
            ->leftJoin('r.image', 'ri')
            ->addSelect('ri')
            ->where('m.sender = :user OR m.receiver = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $list = [];
        foreach ($messages as $message) {
            $isReceived = $message->getReceiver()->getId() === $user->getId();
            $partner = $isReceived ? $message->getSender() : $message->getReceiver();
            $partnerId = $partner->getId();

            // Skip blocked users
            if (in_array($partnerId, $excludeUserIds, true)) {
                continue;
            }

            $isUnread = $isReceived && $message->isWasRead() === false;

            if (!isset($list[$partnerId])) {
                $list[$partnerId] = [
                    'messages' => 1,
                    'unread' => $isUnread ? 1 : 0,
                    'lastMessage' => $message->getCreatedAt(),
                    'user' => $partner,
                ];
                continue;
            }
            ++$list[$partnerId]['messages'];
            if ($isUnread) {
                $list[$partnerId]['unread'] = ($list[$partnerId]['unread'] ?? 0) + 1;
            }
        }

        // Only add new conversation partner if not blocked
        if ($id !== null && !isset($list[$id]) && !in_array($id, $excludeUserIds, true)) {
            $userRepo = $this->getEntityManager()->getRepository(User::class);
            $list[] = [
                'messages' => 0,
                'unread' => 0,
                'lastMessage' => new DateTimeImmutable(),
                'user' => $userRepo->findOneBy(['id' => $id]),
            ];
        }

        return $list;
    }

    public function findEditableForSender(int $messageId, User $sender, DateTimeImmutable $now): ?Message
    {
        $cutoff = $now->modify('-' . Message::EDIT_WINDOW_MINUTES . ' minutes');

        return $this
            ->createQueryBuilder('m')
            ->where('m.id = :id')
            ->andWhere('m.sender = :sender')
            ->andWhere('m.deleted = false')
            ->andWhere('m.createdAt > :cutoff')
            ->setParameter('id', $messageId)
            ->setParameter('sender', $sender)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getMessages(User $user, ?User $partner = null): ?array
    {
        if (!$partner instanceof User) {
            return null;
        }

        return $this
            ->createQueryBuilder('m')
            ->where('(m.sender = :self AND m.receiver = :partner) OR (m.sender = :partner AND m.receiver = :self)')
            ->setParameter('self', $user)
            ->setParameter('partner', $partner)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getMessageCount(User $user): int
    {
        return count(
            $this
                ->createQueryBuilder('m')
                ->where('m.receiver = :self')
                ->setParameter('self', $user)
                ->orderBy('m.createdAt', 'ASC')
                ->getQuery()
                ->getArrayResult(),
        );
    }

    public function hasNewMessages(User $user): bool
    {
        $result = $this
            ->createQueryBuilder('m')
            ->where('m.receiver = :user AND m.wasRead = false')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return count($result) > 0;
    }

    public function markConversationRead(User $user, User $conversationPartner): void
    {
        $this
            ->createQueryBuilder('m')
            ->update(Message::class, 'm')
            ->set('m.wasRead', true)
            ->where('m.receiver = :user AND m.sender = :partner')
            ->setParameter('user', $user)
            ->setParameter('partner', $conversationPartner)
            ->getQuery()
            ->execute();
    }

    /**
     * Get system-wide message statistics.
     *
     * @param array<int>|null $restrictToUserIds Both sender and receiver must be in this set.
     * @return array{total: int, unread: int}
     */
    public function getSystemStats(?array $restrictToUserIds = null): array
    {
        if ($restrictToUserIds === []) {
            return ['total' => 0, 'unread' => 0];
        }

        $totalQb = $this
            ->createQueryBuilder('m')
            ->select('COUNT(m.id)');

        $unreadQb = $this
            ->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.wasRead = false');

        if ($restrictToUserIds !== null) {
            $totalQb
                ->andWhere('IDENTITY(m.sender) IN (:userIds)')
                ->andWhere('IDENTITY(m.receiver) IN (:userIds)')
                ->setParameter('userIds', $restrictToUserIds);

            $unreadQb
                ->andWhere('IDENTITY(m.sender) IN (:userIds)')
                ->andWhere('IDENTITY(m.receiver) IN (:userIds)')
                ->setParameter('userIds', $restrictToUserIds);
        }

        return [
            'total' => (int) $totalQb->getQuery()->getSingleScalarResult(),
            'unread' => (int) $unreadQb->getQuery()->getSingleScalarResult(),
        ];
    }
}
