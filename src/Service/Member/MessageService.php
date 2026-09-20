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
