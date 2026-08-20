<?php declare(strict_types=1);

namespace Tests\Unit\Service\Support;

use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\SupportAudience;
use App\Repository\SupportMessageRepository;
use App\Repository\SupportRequestRepository;
use App\Service\Security\ContentSanitizer;
use App\Service\Support\ThreadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

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
        $repo
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['token' => $token])
            ->willReturn($expected);

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

    private function createService(?SupportRequestRepository $requestRepo = null): ThreadService
    {
        return new ThreadService(
            $this->createStub(EntityManagerInterface::class),
            $requestRepo ?? $this->createStub(SupportRequestRepository::class),
            $this->createStub(SupportMessageRepository::class),
            new ContentSanitizer($this->createStub(HtmlSanitizerInterface::class), $this->createStub(HtmlSanitizerInterface::class)),
            new MockClock('2026-08-19 12:00:00'),
        );
    }
}
