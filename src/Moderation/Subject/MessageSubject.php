<?php declare(strict_types=1);

namespace App\Moderation\Subject;

use App\Entity\Message;
use App\Entity\User;
use App\Moderation\SubjectProviderInterface;
use App\Moderation\SubjectSnapshot;
use App\Service\Member\MessageService;
use Override;

final readonly class MessageSubject implements SubjectProviderInterface
{
    public const string TYPE = 'message';
    private const int EXCERPT_LENGTH = 1000;

    public function __construct(
        private MessageService $messageService,
    ) {}

    #[Override]
    public function getTypeKey(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function describe(int $id, User $reporter): ?SubjectSnapshot
    {
        $message = $this->messageService->findMessage($id);
        if ($message === null || $message->getReceiver()?->getId() !== $reporter->getId()) {
            return null;
        }

        $content = (string) $message->getContent();
        if (str_starts_with($content, Message::SUPPORT_QUESTION_MARKER)) {
            $content = substr($content, strlen(Message::SUPPORT_QUESTION_MARKER));
        }
        $sender = $message->getSender();

        return new SubjectSnapshot(label: (string) $sender?->getName(), excerpt: mb_substr(trim($content), 0, self::EXCERPT_LENGTH), author: $sender);
    }

    #[Override]
    public function getAdminPath(int $id): ?string
    {
        return null;
    }

    #[Override]
    public function getRemoveLabelKey(): ?string
    {
        return null;
    }

    #[Override]
    public function remove(int $id): void {}
}
