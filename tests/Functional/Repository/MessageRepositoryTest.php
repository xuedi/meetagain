<?php declare(strict_types=1);

namespace Tests\Functional\Repository;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MessageRepositoryTest extends KernelTestCase
{
    private const string SENDER_EMAIL = 'Admin@example.org';
    private const string PARTNER_EMAIL = 'Crystal.Liu@example.org';
    private const string FIXED_NOW = '2026-05-09 12:00:00';

    private EntityManagerInterface $em;
    private MessageRepository $repo;
    private User $sender;
    private User $partner;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(MessageRepository::class);
        $userRepo = $this->em->getRepository(User::class);
        $this->sender = $userRepo->findOneBy(['email' => self::SENDER_EMAIL]);
        $this->partner = $userRepo->findOneBy(['email' => self::PARTNER_EMAIL]);
        if (!$this->sender instanceof User || !$this->partner instanceof User) {
            self::fail('Required fixture users missing');
        }
    }

    /**
     * @param 'within'|'outside'|'wrongSender'|'deleted'|'unknownId' $case
     */
    #[DataProvider('cases')]
    public function testFindEditableForSender(string $case, bool $expectMatch): void
    {
        // Arrange
        $now = new DateTimeImmutable(self::FIXED_NOW);
        $msg = $this->seedMessage($this->sender, $this->partner, $now->modify('-2 minutes'), 'hello world', deleted: false);

        $lookupId = $msg->getId();
        $lookupSender = $this->sender;

        switch ($case) {
            case 'within':
                break;
            case 'outside':
                $msg->setCreatedAt($now->modify('-15 minutes'));
                $this->em->flush();
                break;
            case 'wrongSender':
                $lookupSender = $this->partner;
                break;
            case 'deleted':
                $msg->setDeleted(true);
                $this->em->flush();
                break;
            case 'unknownId':
                $lookupId = 999999999;
                break;
        }

        // Act
        $found = $this->repo->findEditableForSender($lookupId, $lookupSender, $now);

        // Assert
        if ($expectMatch) {
            self::assertNotNull($found);
            self::assertSame($msg->getId(), $found->getId());
        } else {
            self::assertNull($found);
        }

        // Cleanup
        $this->em->remove($msg);
        $this->em->flush();
    }

    public static function cases(): array
    {
        return [
            'sender within window' => ['within', true],
            'sender outside window' => ['outside', false],
            'wrong sender' => ['wrongSender', false],
            'deleted message' => ['deleted', false],
            'unknown id' => ['unknownId', false],
        ];
    }

    private function seedMessage(User $sender, User $receiver, DateTimeImmutable $createdAt, string $content, bool $deleted): Message
    {
        $msg = new Message();
        $msg->setSender($sender);
        $msg->setReceiver($receiver);
        $msg->setCreatedAt($createdAt);
        $msg->setContent($content);
        $msg->setDeleted($deleted);
        $msg->setWasRead(false);
        $this->em->persist($msg);
        $this->em->flush();

        return $msg;
    }
}
