<?php declare(strict_types=1);

namespace Tests\Unit\Service\Support;

use App\Activity\ActivityService;
use App\Activity\Messages\SendMessage;
use App\Emails\Types\SupportResponseEmail;
use App\Entity\Message;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\SupportAudience;
use App\Enum\SupportChannel;
use App\Enum\SupportRequestStatus;
use App\Repository\SupportMessageRepository;
use App\Repository\SupportRequestRepository;
use App\Service\Security\ContentSanitizer;
use App\Service\Support\ThreadService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Tests\Unit\Stubs\UserStub;

class ThreadServiceTest extends TestCase
{
    public function testMintTokenIsSixtyFourLowercaseHexCharacters(): void
    {
        // Arrange
        $service = $this->createService();

        // Act
        $token = $service->mintToken();

        // Assert
        static::assertSame(64, strlen($token));
        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testMintTokenDoesNotRepeat(): void
    {
        // Arrange
        $service = $this->createService();

        // Act
        $tokens = [$service->mintToken(), $service->mintToken(), $service->mintToken()];

        // Assert
        static::assertCount(3, array_unique($tokens));
    }

    public function testFindByTokenLooksTheRequestUpByItsToken(): void
    {
        // Arrange
        $expected = new SupportRequest();
        $token = 'a1' . str_repeat('0', 62);

        $repo = $this->createMock(SupportRequestRepository::class);
        $repo->expects($this->once())->method('findOneBy')->with(['token' => $token])->willReturn($expected);

        $service = $this->createService(requestRepo: $repo);

        // Act
        $found = $service->findByToken($token);

        // Assert
        static::assertSame($expected, $found);
    }

    public function testInvitingTheAdminsLeavesTheAudienceAloneSoTheStewardKeepsAccess(): void
    {
        // Arrange
        $request = new SupportRequest();
        $request->setAudience(SupportAudience::Organizer);
        $steward = new User();
        $service = $this->createService();

        // Act
        $service->inviteAdmins($request, $steward);

        // Assert
        static::assertSame(SupportAudience::Organizer, $request->getAudience());
        static::assertTrue($request->hasInvitedAdmins());
        static::assertSame($steward, $request->getInvitedAdminsBy());
        static::assertFalse($request->canInviteAdmins());
        static::assertSame('2026-08-19 12:00:00', $request->getLastActivityAt()?->format('Y-m-d H:i:s'));
    }

    public function testAnswerToAMemberMirrorsTheQuestionAndTheAnswerIntoTheInboxOnTheFirstReply(): void
    {
        // Arrange
        $member = new UserStub()->setId(5);
        $admin = new UserStub()->setId(9);
        $request = new SupportRequest()
            ->setChannel(SupportChannel::Message)
            ->setRequester($member)
            ->setMessage('Where is my event?')
            ->setCreatedAt(new DateTimeImmutable('2026-08-18 09:00'));
        $inbox = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$inbox): void {
            if ($entity instanceof Message) {
                $inbox[] = $entity;
            }
        });
        $email = $this->createMock(SupportResponseEmail::class);
        $email->expects(self::never())->method('send');
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::once())->method('log')->with(SendMessage::TYPE, $admin, ['user_id' => 5]);
        $service = $this->createService(em: $em, responseEmail: $email, activity: $activity);

        // Act
        $service->answer($request, 'Here it is.', $admin);

        // Assert
        static::assertCount(2, $inbox);
        static::assertSame(Message::SUPPORT_QUESTION_MARKER . 'Where is my event?', $inbox[0]->getContent());
        static::assertSame($member, $inbox[0]->getSender());
        static::assertSame('Here it is.', $inbox[1]->getContent());
        static::assertSame($member, $inbox[1]->getReceiver());
        static::assertSame(SupportRequestStatus::Replied, $request->getStatus());
        static::assertFalse($service->hasLostRequester($request));
    }

    public function testAnswerToAMemberAfterTheFirstReplyMirrorsOnlyTheAnswer(): void
    {
        // Arrange
        $admin = new UserStub()->setId(9);
        $request = new SupportRequest()
            ->setChannel(SupportChannel::Message)
            ->setRequester(new UserStub()->setId(5))
            ->setRespondedBy($admin);
        $inbox = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$inbox): void {
            if ($entity instanceof Message) {
                $inbox[] = $entity;
            }
        });
        $service = $this->createService(em: $em);

        // Act
        $service->answer($request, 'Second answer.', $admin);

        // Assert
        static::assertCount(1, $inbox);
        static::assertSame('Second answer.', $inbox[0]->getContent());
    }

    public function testAnswerToAConfirmedGuestSendsTheResponseMail(): void
    {
        // Arrange
        $request = new SupportRequest()
            ->setChannel(SupportChannel::Thread)
            ->setEmail('guest@example.org')
            ->setEmailVerifiedAt(new DateTimeImmutable('2026-08-18'));
        $email = $this->createMock(SupportResponseEmail::class);
        $email->expects(self::once())->method('send')->with(['request' => $request, 'response' => 'Answer.']);
        $service = $this->createService(responseEmail: $email);

        // Act
        $service->answer($request, 'Answer.', new UserStub()->setId(9));
    }

    public function testAnswerToAnUnconfirmedGuestSendsNoMail(): void
    {
        // Arrange
        $request = new SupportRequest()
            ->setChannel(SupportChannel::Thread)
            ->setEmail('guest@example.org');
        $email = $this->createMock(SupportResponseEmail::class);
        $email->expects(self::never())->method('send');
        $service = $this->createService(responseEmail: $email);

        // Act
        $service->answer($request, 'Answer.', new UserStub()->setId(9));
    }

    public function testAMemberRequestWhoseAccountIsGoneHasLostItsRequester(): void
    {
        // Arrange
        $request = new SupportRequest()->setChannel(SupportChannel::Message);
        $service = $this->createService();

        // Act
        $lost = $service->hasLostRequester($request);

        // Assert
        static::assertTrue($lost);
    }

    public function testMarkReadOnlyMovesANewRequest(): void
    {
        // Arrange
        $new = new SupportRequest();
        $reopened = new SupportRequest()->setStatus(SupportRequestStatus::Reopened);
        $service = $this->createService();

        // Act
        $service->markRead($new);
        $service->markRead($reopened);

        // Assert
        static::assertSame(SupportRequestStatus::Read, $new->getStatus());
        static::assertSame(SupportRequestStatus::Reopened, $reopened->getStatus());
    }

    private function createService(
        ?SupportRequestRepository $requestRepo = null,
        ?EntityManagerInterface $em = null,
        ?SupportResponseEmail $responseEmail = null,
        ?ActivityService $activity = null,
    ): ThreadService {
        $config = new HtmlSanitizerConfig()->allowSafeElements();

        return new ThreadService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $requestRepo ?? $this->createStub(SupportRequestRepository::class),
            $this->createStub(SupportMessageRepository::class),
            new ContentSanitizer(new HtmlSanitizer($config), new HtmlSanitizer($config)),
            new MockClock('2026-08-19 12:00:00'),
            $responseEmail ?? $this->createStub(SupportResponseEmail::class),
            $activity ?? $this->createStub(ActivityService::class),
        );
    }
}
