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

    public function getConversations(User $user, ?int $id = null): array
    {
        $messages = $this->createQueryBuilder('m')
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
            $partner = $message->getSender()->getId() === $user->getId()
                ? $message->getReceiver()
                : $message->getSender();
            $partnerId = $partner->getId();

            if (!isset($list[$partnerId])) {
                $list[$partnerId] = [
                    'messages' => 1,
                    'unread' => $message->isWasRead() === false ? 1 : 0,
                    'lastMessage' => $message->getCreatedAt(),
                    'user' => $partner,
                ];
            } else {
                ++$list[$partnerId]['messages'];
                if ($message->isWasRead() === false) {
                    ++$list[$partnerId]['unread'];
                }
            }
        }

        if ($id !== null && !isset($list[$id])) {
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

    public function getMessages(User $user, ?User $partner = null): ?array
    {
        if (!($partner instanceof User)) {
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

    public function getMessageCount(User $user): int
    {
        return count(
            $this->createQueryBuilder('m')
                ->where('m.receiver = :self')
                ->setParameter('self', $user)
                ->orderBy('m.createdAt', 'ASC')
                ->getQuery()
                ->getArrayResult(),
        );
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
