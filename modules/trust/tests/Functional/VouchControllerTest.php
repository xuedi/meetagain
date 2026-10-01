<?php declare(strict_types=1);

namespace Module\Trust\Tests\Functional;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Module\Trust\Tests\Stub\AccessProvider;
use Module\Trust\Tests\Stub\ContextDescriber;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Tests\Module\Members;

final class VouchControllerTest extends WebTestCase
{
    private const string URL = '/en/trust/vouch';
    private const string SAME_HOST_PAGE = 'http://localhost/en/somewhere';

    private KernelBrowser $client;

    private User $voucher;

    private User $target;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $members = new Members($this->client->getContainer()->get(EntityManagerInterface::class));
        $this->voucher = $members->member('Voucher');
        $this->target = $members->member('Target');
        $this->client->loginUser($this->voucher);
    }

    public function testAVouchIsStoredAndReturnsToTheContextsPage(): void
    {
        // Arrange
        $targetId = (int) $this->target->getId();

        // Act
        $this->vouch($targetId);

        // Assert
        self::assertResponseRedirects('/');
        self::assertSame(1, $this->grantsTo($targetId));
    }

    public function testAMemberCannotVouchForThemselves(): void
    {
        // Arrange
        $voucherId = (int) $this->voucher->getId();

        // Act
        $this->vouch($voucherId);

        // Assert
        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->grantsTo($voucherId));
    }

    public function testAnUnknownContextIsNotFound(): void
    {
        // Arrange
        $targetId = (int) $this->target->getId();

        // Act
        $this->vouch($targetId, context: 'never-declared');

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->grantsTo($targetId));
    }

    public function testAMemberOutsideTheContextCannotBeVouchedFor(): void
    {
        // Arrange
        $targetId = (int) $this->target->getId();
        $this->client->getContainer()->get(AccessProvider::class)->outsiderIds = [$targetId];

        // Act
        $this->vouch($targetId);

        // Assert
        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->grantsTo($targetId));
    }

    public function testWithoutAReturnUrlItGoesBackToTheSameHostPage(): void
    {
        // Arrange
        $this->client->getContainer()->get(ContextDescriber::class)->returnUrl = null;

        // Act
        $this->vouch((int) $this->target->getId(), referer: self::SAME_HOST_PAGE);

        // Assert
        self::assertResponseRedirects(self::SAME_HOST_PAGE);
    }

    public function testWithoutAReturnUrlAForeignRefererIsNeverFollowed(): void
    {
        // Arrange
        $this->client->getContainer()->get(ContextDescriber::class)->returnUrl = null;

        // Act
        $this->vouch((int) $this->target->getId(), referer: 'https://evil.example/phish');

        // Assert
        self::assertResponseRedirects('/');
    }

    private function vouch(int $targetId, string $context = ContextDescriber::CONTEXT, ?string $referer = null): void
    {
        $this->client->request(
            'POST',
            self::URL,
            ['_token' => $this->csrfToken('trust_vouch' . $targetId), 'user' => $targetId, 'context' => $context, 'level' => 'trusted'],
            server: $referer === null ? [] : ['HTTP_REFERER' => $referer],
        );
    }

    private function grantsTo(int $userId): int
    {
        return (int) $this->client->getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM mod_trust_grant WHERE to_user_id = ?', [$userId]);
    }

    private function csrfToken(string $tokenId): string
    {
        $container = $this->client->getContainer();
        $session = $container->get('session.factory')->createSession();
        $session->setId((string) $this->client->getCookieJar()->get('MOCKSESSID')?->getValue());
        $session->start();

        $request = new Request();
        $request->setSession($session);
        $container->get('request_stack')->push($request);
        $token = $container->get('security.csrf.token_manager')->getToken($tokenId)->getValue();
        $session->save();
        $container->get('request_stack')->pop();

        return $token;
    }
}
