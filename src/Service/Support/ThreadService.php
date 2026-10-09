<?php declare(strict_types=1);

namespace App\Service\Support;

use App\Activity\ActivityService;
use App\Activity\Messages\SendMessage;
use App\Emails\Types\SupportResponseEmail;
use App\Entity\Message;
use App\Entity\SupportMessage;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\SupportChannel;
use App\Enum\SupportMessageAuthor;
use App\Enum\SupportRequestStatus;
use App\Repository\SupportMessageRepository;
use App\Repository\SupportRequestRepository;
use App\Service\Security\ContentSanitizer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

readonly class ThreadService
{
    public const int MAX_MESSAGES = 50;
    public const int MAX_TRAILING_REQUESTER_MESSAGES = 5;
    public const int EMAIL_VERIFY_TTL_HOURS = 24;

    public function __construct(
        private EntityManagerInterface $em,
        private SupportRequestRepository $requestRepo,
        private SupportMessageRepository $messageRepo,
        private ContentSanitizer $sanitizer,
        private ClockInterface $clock,
        private SupportResponseEmail $responseEmail,
        private ActivityService $activityService,
    ) {}

    public function findRequest(int $id): ?SupportRequest
    {
        return $this->requestRepo->find($id);
    }

    /** @return SupportRequest[] */
    public function findRecentForRequester(User $requester, int $limit): array
    {
        return $this->requestRepo->findRecentForRequester($requester, $limit);
    }

    public function mintToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function findByToken(#[SensitiveParameter] string $token): ?SupportRequest
    {
        return $this->requestRepo->findOneBy(['token' => $token]);
    }

    public function openThread(SupportRequest $request, ?string $ipAddress): ?string
    {
        $now = $this->clock->now();
        $channel = $request->getRequester() instanceof User ? SupportChannel::Message : SupportChannel::Thread;
        $token = $channel === SupportChannel::Thread ? $this->mintToken() : null;

        $request->setChannel($channel);
        $request->setToken($token);
        $request->setLastActivityAt($now);
        $this->em->persist($request);

        $this->appendMessage($request, SupportMessageAuthor::Requester, $request->getMessage(), null, $ipAddress, $now);
        $this->em->flush();

        return $token;
    }

    /** @return SupportMessage[] */
    public function getThread(SupportRequest $request): array
    {
        return $this->messageRepo->findThread($request);
    }

    public function isFull(SupportRequest $request): bool
    {
        return $this->messageRepo->countForRequest($request) >= self::MAX_MESSAGES;
    }

    public function isAwaitingAnswer(SupportRequest $request): bool
    {
        return $this->messageRepo->countTrailingRequesterMessages($request) >= self::MAX_TRAILING_REQUESTER_MESSAGES;
    }

    public function postRequesterMessage(SupportRequest $request, string $body, ?string $ipAddress): SupportMessage
    {
        $now = $this->clock->now();
        $message = $this->appendMessage($request, SupportMessageAuthor::Requester, $this->sanitizer->escape($body), null, $ipAddress, $now);

        $request->setLastActivityAt($now);
        if (!$request->isNew()) {
            $request->setStatus(SupportRequestStatus::Reopened);
        }

        $this->em->persist($request);
        $this->em->flush();

        return $message;
    }

    public function postAdminMessage(SupportRequest $request, string $body, User $actingAdmin): SupportMessage
    {
        $now = $this->clock->now();
        $message = $this->appendMessage($request, SupportMessageAuthor::Admin, $this->sanitizer->basic($body), $actingAdmin, null, $now);

        if (!$request->getRespondedBy() instanceof User) {
            $request->setRespondedBy($actingAdmin);
        }
        $request->setStatus(SupportRequestStatus::Replied);
        $request->setLastActivityAt($now);

        $this->em->persist($request);
        $this->em->flush();

        return $message;
    }

    public function markRead(SupportRequest $request): void
    {
        if (!$request->isNew()) {
            return;
        }

        $request->setStatus(SupportRequestStatus::Read);
        $this->em->persist($request);
        $this->em->flush();
    }

    public function answer(SupportRequest $request, string $body, User $actingAdmin): SupportMessage
    {
        $isFirstResponse = !$request->getRespondedBy() instanceof User;
        $message = $this->postAdminMessage($request, $body, $actingAdmin);

        if ($request->getChannel() === SupportChannel::Message) {
            $this->mirrorToInbox($request, $message->getContent(), $isFirstResponse);
        }

        if ($request->getChannel() === SupportChannel::Thread && $request->isEmailVerified()) {
            $this->responseEmail->send(['request' => $request, 'response' => $message->getContent()]);
        }

        return $message;
    }

    public function hasLostRequester(SupportRequest $request): bool
    {
        return $request->getChannel() === SupportChannel::Message && !$request->getRequester() instanceof User;
    }

    public function inviteAdmins(SupportRequest $request, User $invitedBy): void
    {
        $now = $this->clock->now();
        $request->setInvitedAdminsAt($now);
        $request->setInvitedAdminsBy($invitedBy);
        $request->setLastActivityAt($now);

        $this->em->persist($request);
        $this->em->flush();
    }

    public function resolve(SupportRequest $request): void
    {
        $now = $this->clock->now();
        $request->setStatus(SupportRequestStatus::Resolved);
        $request->setResolvedAt($now);
        $request->setLastActivityAt($now);

        $this->em->persist($request);
        $this->em->flush();
    }

    public function reopen(SupportRequest $request): void
    {
        $request->setStatus(SupportRequestStatus::Reopened);
        $request->setResolvedAt(null);
        $request->setLastActivityAt($this->clock->now());

        $this->em->persist($request);
        $this->em->flush();
    }

    public function startEmailVerification(SupportRequest $request, string $email): string
    {
        $now = $this->clock->now();
        $token = $this->mintToken();

        $request->setEmail($email);
        $request->setEmailVerifiedAt(null);
        $request->setEmailVerifyToken($token);
        $request->setEmailVerifyExpiresAt($now->modify(sprintf('+%d hours', self::EMAIL_VERIFY_TTL_HOURS)));

        $this->em->persist($request);
        $this->em->flush();

        return $token;
    }

    public function confirmEmail(#[SensitiveParameter] string $token): ?SupportRequest
    {
        $request = $this->requestRepo->findOneBy(['emailVerifyToken' => $token]);
        if (!$request instanceof SupportRequest) {
            return null;
        }

        $expiresAt = $request->getEmailVerifyExpiresAt();
        if (!$expiresAt instanceof DateTimeImmutable || $expiresAt < $this->clock->now()) {
            return null;
        }

        $request->setEmailVerifiedAt($this->clock->now());
        $request->setEmailVerifyToken(null);
        $request->setEmailVerifyExpiresAt(null);

        $this->em->persist($request);
        $this->em->flush();

        return $request;
    }

    public function clearEmailVerification(SupportRequest $request): void
    {
        $request->setEmailVerifyToken(null);
        $request->setEmailVerifyExpiresAt(null);
        $this->em->persist($request);
    }

    private function mirrorToInbox(SupportRequest $request, string $response, bool $isFirstResponse): void
    {
        $receiver = $request->getRequester();
        $owner = $request->getRespondedBy();
        if (!$receiver instanceof User || !$owner instanceof User) {
            return;
        }

        if ($isFirstResponse) {
            $question = new Message();
            $question->setDeleted(false);
            $question->setWasRead(true);
            $question->setSender($receiver);
            $question->setReceiver($owner);
            $question->setCreatedAt($request->getCreatedAt());
            $question->setContent(Message::SUPPORT_QUESTION_MARKER . $request->getMessage());
            $this->em->persist($question);
        }

        $answer = new Message();
        $answer->setDeleted(false);
        $answer->setWasRead(false);
        $answer->setSender($owner);
        $answer->setReceiver($receiver);
        $answer->setCreatedAt($this->clock->now());
        $answer->setContent($response);
        $this->em->persist($answer);
        $this->em->flush();

        $this->activityService->log(SendMessage::TYPE, $owner, ['user_id' => $receiver->getId()]);
    }

    private function appendMessage(
        SupportRequest $request,
        SupportMessageAuthor $author,
        string $content,
        ?User $authorUser,
        ?string $ipAddress,
        DateTimeImmutable $createdAt,
    ): SupportMessage {
        $message = new SupportMessage();
        $message->setSupportRequest($request);
        $message->setAuthor($author);
        $message->setAuthorUser($authorUser);
        $message->setContent($content);
        $message->setIpAddress($ipAddress);
        $message->setCreatedAt($createdAt);
        $this->em->persist($message);

        return $message;
    }
}
