<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\User;
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

    public function getConversations(User $user): array
    {
        $list = [];

        $result = $this->createQueryBuilder('m')
            ->select('m.createdAt, us.id as senderId, ur.id as receiverId, m.wasRead') //
            ->leftJoin('m.receiver', 'us')  // join user
            ->leftJoin('m.sender', 'ur')  // join user
            ->where('m.sender = :user OR m.receiver = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $userRepo = $this->getEntityManager()->getRepository(User::class);
        foreach ($result as $item) {
            $partnerId = $user->getId() === $item['senderId'] ? $item['receiverId'] : $item['senderId'];
            if (!isset($list[$partnerId])) {
                $list[$partnerId] = [
                    'messages' => 1,
                    'unread' => $item['wasRead'] === false ? 1 : 0,
                    'lastMessage' => $item['createdAt'],
                    'user' => $userRepo->findOneBy(['id' => $partnerId]),
                ];
            } else {
                $list[$partnerId]['messages']++;
                if ($item['wasRead'] === false) {
                    $list[$partnerId]['unread']++;
                }
            }
        }

        return $list;
    }

    public function getMessages(User $user, User|null $partner): ?array
    {
        if ($partner === null) {
            return null;
        }

        return $this->createQueryBuilder('m')
            ->where('(m.sender = :self AND m.receiver = :partner) OR (m.sender = :partner AND m.receiver = :self)')
            ->setParameter('self', $user)
            ->setParameter('partner', $partner)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function hasNewMessages(User $user): bool
    {
        $result = $this->createQueryBuilder('m')
            ->where('m.receiver = :user AND m.wasRead = false')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return count($result) > 0;
    }

    public function markConversationRead(User $user, User $conversationPartner): void
    {
        $this->createQueryBuilder('m')
            ->update(Message::class, 'm')
            ->set('m.wasRead', true)
            ->where('m.receiver = :user AND m.sender = :partner')
            ->setParameter('user', $user)
            ->setParameter('partner', $conversationPartner)
            ->getQuery()
            ->execute();
    }
}
