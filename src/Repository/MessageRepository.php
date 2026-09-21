<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Message;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    private const string PARTNER_ID = 'CASE WHEN IDENTITY(m.sender) = :selfId THEN IDENTITY(m.receiver) ELSE IDENTITY(m.sender) END';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @param int[] $excludeUserIds User IDs to exclude from conversation list (e.g., blocked users)
     *
     * @return array<int, array{messages: int, unread: int, lastMessage: DateTimeImmutable, user: User}>
     */
    public function getConversations(User $user, ?int $id = null, array $excludeUserIds = [], ?int $limit = null, int $offset = 0): array
    {
        $rows = $this
            ->conversationQuery($user, $excludeUserIds)
            ->select(
                self::PARTNER_ID . ' AS partnerId',
                'COUNT(m.id) AS messages',
                'SUM(CASE WHEN IDENTITY(m.receiver) = :selfId AND m.wasRead = false THEN 1 ELSE 0 END) AS unread',
                'MAX(m.createdAt) AS lastMessage',
            )
            ->groupBy('partnerId')
            ->orderBy('lastMessage', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $partners = $this->findPartners(array_map(static fn(array $row): int => (int) $row['partnerId'], $rows));

        $list = [];
        foreach ($rows as $row) {
            $partnerId = (int) $row['partnerId'];
            if (!isset($partners[$partnerId])) {
                continue;
            }

            $list[$partnerId] = [
                'messages' => (int) $row['messages'],
                'unread' => (int) $row['unread'],
                'lastMessage' => new DateTimeImmutable((string) $row['lastMessage']),
                'user' => $partners[$partnerId],
            ];
        }

        if ($id !== null && !isset($list[$id]) && !in_array($id, $excludeUserIds, true)) {
            $partner = $this->getEntityManager()->getRepository(User::class)->find($id);
            if ($partner instanceof User) {
                $list[$id] = [
                    'messages' => 0,
                    'unread' => 0,
                    'lastMessage' => new DateTimeImmutable(),
                    'user' => $partner,
                ];
            }
        }

        return $list;
    }

    /**
     * @param int[] $excludeUserIds
     */
    public function countConversations(User $user, array $excludeUserIds = []): int
    {
        $rows = $this
            ->conversationQuery($user, $excludeUserIds)
            ->select(self::PARTNER_ID . ' AS partnerId')
            ->groupBy('partnerId')
            ->getQuery()
            ->getArrayResult();

        return count($rows);
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

        return $this->threadQuery($user, $partner)->orderBy('m.createdAt', 'ASC')->getQuery()->getResult();
    }

    /**
     * @return Message[] Oldest first.
     */
    public function getThreadPage(User $user, User $partner, int $limit, int $offset = 0): array
    {
        return $this
            ->threadQuery($user, $partner)
            ->orderBy('m.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countThread(User $user, User $partner): int
    {
        return (int) $this->threadQuery($user, $partner)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();
    }

    private function threadQuery(User $user, User $partner): QueryBuilder
    {
        return $this
            ->createQueryBuilder('m')
            ->where('(m.sender = :self AND m.receiver = :partner) OR (m.sender = :partner AND m.receiver = :self)')
            ->setParameter('self', $user)
            ->setParameter('partner', $partner);
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
     * @return list<array{sender: User, receiver: User, firstUnread: DateTimeImmutable}>
     */
    public function findUnremindedPairs(?DateTimeImmutable $firstUnreadAfter, DateTimeImmutable $firstUnreadUntil): array
    {
        $qb = $this
            ->createQueryBuilder('m')
            ->select('IDENTITY(m.sender) AS senderId', 'IDENTITY(m.receiver) AS receiverId', 'MIN(m.createdAt) AS firstUnread')
            ->where('m.wasRead = false')
            ->andWhere('m.deleted = false')
            ->andWhere('m.reminderSentAt IS NULL')
            ->groupBy('m.sender')
            ->addGroupBy('m.receiver')
            ->having('MIN(m.createdAt) <= :until')
            ->setParameter('until', $firstUnreadUntil)
            ->orderBy('firstUnread', 'ASC');

        if ($firstUnreadAfter instanceof DateTimeImmutable) {
            $qb->andHaving('MIN(m.createdAt) > :after')->setParameter('after', $firstUnreadAfter);
        }

        $rows = $qb->getQuery()->getArrayResult();
        $userIds = [];
        foreach ($rows as $row) {
            $userIds[] = (int) $row['senderId'];
            $userIds[] = (int) $row['receiverId'];
        }
        $users = $this->findPartners(array_values(array_unique($userIds)));

        $pairs = [];
        foreach ($rows as $row) {
            $sender = $users[(int) $row['senderId']] ?? null;
            $receiver = $users[(int) $row['receiverId']] ?? null;
            if ($sender === null || $receiver === null) {
                continue;
            }
            $pairs[] = [
                'sender' => $sender,
                'receiver' => $receiver,
                'firstUnread' => new DateTimeImmutable((string) $row['firstUnread']),
            ];
        }

        return $pairs;
    }

    public function markReminderSent(User $sender, User $receiver, DateTimeImmutable $at): void
    {
        $this
            ->createQueryBuilder('m')
            ->update(Message::class, 'm')
            ->set('m.reminderSentAt', ':at')
            ->where('m.sender = :sender AND m.receiver = :receiver')
            ->andWhere('m.wasRead = false')
            ->andWhere('m.reminderSentAt IS NULL')
            ->setParameter('at', $at)
            ->setParameter('sender', $sender)
            ->setParameter('receiver', $receiver)
            ->getQuery()
            ->execute();
    }

    /**
     * @param array<int>|null $restrictToUserIds Both sender and receiver must be in this set.
     * @return array{total: int, unread: int}
     */
    public function getSystemStats(?array $restrictToUserIds = null): array
    {
        if ($restrictToUserIds === []) {
            return ['total' => 0, 'unread' => 0];
        }

        $totalQb = $this->createQueryBuilder('m')->select('COUNT(m.id)');
        $unreadQb = $this->createQueryBuilder('m')->select('COUNT(m.id)')->where('m.wasRead = false');

        if ($restrictToUserIds !== null) {
            $totalQb->andWhere('IDENTITY(m.sender) IN (:userIds)')->andWhere('IDENTITY(m.receiver) IN (:userIds)')->setParameter('userIds', $restrictToUserIds);

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

    /**
     * @param int[] $excludeUserIds
     */
    private function conversationQuery(User $user, array $excludeUserIds): QueryBuilder
    {
        $qb = $this
            ->createQueryBuilder('m')
            ->where('m.sender = :self OR m.receiver = :self')
            ->setParameter('self', $user)
            ->setParameter('selfId', $user->getId());

        if ($excludeUserIds !== []) {
            $qb
                ->andWhere('IDENTITY(m.sender) NOT IN (:excluded)')
                ->andWhere('IDENTITY(m.receiver) NOT IN (:excluded)')
                ->setParameter('excluded', $excludeUserIds);
        }

        return $qb;
    }

    /**
     * @param int[] $partnerIds
     *
     * @return array<int, User>
     */
    private function findPartners(array $partnerIds): array
    {
        if ($partnerIds === []) {
            return [];
        }

        $users = $this
            ->getEntityManager()
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->leftJoin('u.image', 'ui')
            ->addSelect('ui')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $partnerIds)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($users as $user) {
            $byId[(int) $user->getId()] = $user;
        }

        return $byId;
    }
}
