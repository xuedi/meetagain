<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Activity\Messages\Registered;
use App\Entity\Activity;
use App\Entity\EmailBlocklistEntry;
use App\Entity\EmailQueue;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Service\Config\ConfigService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;

class RegistrationTest extends WebTestCase
{
    private const string REGISTER_PATH = '/en/register';
    private const string LOGIN_PATH = '/en/login';
    private const string NEW_EMAIL = 'brand.new.member@example.net';
    private const string NEW_PASSWORD = 'sufficiently-long';
    private const string PENDING_APPROVAL_EMAIL = 'Abraham.Baker@example.org';
    private const string FIXTURE_PASSWORD = '1234';

    public function testRegisterPageLoads(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', self::REGISTER_PATH);

        // Assert
        static::assertResponseIsSuccessful();
        static::assertGreaterThan(0, $crawler->filter('form')->count(), 'Register form should exist');
    }

    public function testValidRegistrationCreatesPendingUser(): void
    {
        // Arrange
        $client = static::createClient();
        $crawler = $client->request('GET', self::REGISTER_PATH);

        // Act
        $client->submit($this->registrationForm($crawler));

        // Assert
        static::assertResponseIsSuccessful();
        $user = $this->findUser($client, self::NEW_EMAIL);
        static::assertInstanceOf(User::class, $user);
        static::assertSame(UserStatus::Registered, $user->getStatus());
        static::assertFalse($user->isVerified());
        static::assertNotNull($user->getRegcode());
        static::assertNotSame(self::NEW_PASSWORD, $user->getPassword());
        static::assertGreaterThan(new DateTimeImmutable(), $user->getRegcodeExpiresAt());
    }

    public function testValidRegistrationQueuesVerificationEmailAndLogsActivity(): void
    {
        // Arrange
        $client = static::createClient();
        $crawler = $client->request('GET', self::REGISTER_PATH);

        // Act
        $client->submit($this->registrationForm($crawler));

        // Assert
        $em = $this->em($client);
        $user = $this->findUser($client, self::NEW_EMAIL);
        static::assertInstanceOf(User::class, $user);

        $queued = $em->getRepository(EmailQueue::class)->findOneBy(['recipient' => self::NEW_EMAIL]);
        static::assertNotNull($queued, 'Verification email should be queued');

        $activity = $em->getRepository(Activity::class)->findOneBy(['user' => $user, 'type' => Registered::TYPE]);
        static::assertNotNull($activity, 'Registration activity should be logged');
    }

    /**
     * @param array<string, string|bool> $overrides
     */
    #[DataProvider('invalidRegistrationProvider')]
    public function testInvalidRegistrationIsRejected(array $overrides, string $email): void
    {
        // Arrange
        $client = static::createClient();
        $crawler = $client->request('GET', self::REGISTER_PATH);

        // Act
        $client->submit($this->registrationForm($crawler, $overrides));

        // Assert
        static::assertResponseStatusCodeSame(422);
        static::assertNull($this->findUser($client, $email), 'No user row may be created');
    }

    public static function invalidRegistrationProvider(): iterable
    {
        yield 'empty email' => [['registration[email]' => ''], ''];
        yield 'malformed email' => [['registration[email]' => 'not-an-email'], 'not-an-email'];
        yield 'empty name' => [['registration[name]' => ''], self::NEW_EMAIL];
        yield 'blank password' => [['registration[plainPassword]' => ''], self::NEW_EMAIL];
        yield 'password too short' => [['registration[plainPassword]' => 'abc'], self::NEW_EMAIL];
        yield 'terms not accepted' => [['registration[agreeTerms]' => false], self::NEW_EMAIL];
    }

    public function testDuplicateEmailIsRejected(): void
    {
        // Arrange
        $client = static::createClient();
        $crawler = $client->request('GET', self::REGISTER_PATH);

        // Act
        $client->submit($this->registrationForm($crawler, ['registration[email]' => self::PENDING_APPROVAL_EMAIL]));

        // Assert
        static::assertResponseStatusCodeSame(422);
        $matches = $this->em($client)->getRepository(User::class)->findBy(['email' => self::PENDING_APPROVAL_EMAIL]);
        static::assertCount(1, $matches, 'The existing account must not be duplicated');
    }

    public function testBlocklistedEmailIsRejected(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $this->em($client);
        $entry = new EmailBlocklistEntry();
        $entry->setEmail(self::NEW_EMAIL);
        $entry->setReason('spam');
        $entry->setAddedAt(new DateTimeImmutable());
        $em->persist($entry);
        $em->flush();

        $crawler = $client->request('GET', self::REGISTER_PATH);

        // Act
        $client->submit($this->registrationForm($crawler));

        // Assert
        static::assertResponseIsSuccessful();
        static::assertNull($this->findUser($client, self::NEW_EMAIL), 'Blocklisted address must not create an account');
    }

    public function testVerificationLinkActivatesAccountWhenRegistrationIsAutomatic(): void
    {
        // Arrange
        $client = static::createClient();
        static::getContainer()->get(ConfigService::class)->setString('automatic_registration', 'true');
        $code = $this->register($client);

        // Act
        $client->request('GET', '/en/register/verify/' . $code);

        // Assert
        static::assertResponseIsSuccessful();
        $user = $this->findUser($client, self::NEW_EMAIL);
        static::assertInstanceOf(User::class, $user);
        static::assertSame(UserStatus::Active, $user->getStatus());
    }

    public function testVerificationLinkLeavesAccountAwaitingApprovalWhenRegistrationIsManual(): void
    {
        // Arrange
        $client = static::createClient();
        static::getContainer()->get(ConfigService::class)->setString('automatic_registration', 'false');
        $code = $this->register($client);

        // Act
        $client->request('GET', '/en/register/verify/' . $code);

        // Assert
        static::assertResponseIsSuccessful();
        $user = $this->findUser($client, self::NEW_EMAIL);
        static::assertInstanceOf(User::class, $user);
        static::assertSame(UserStatus::EmailVerified, $user->getStatus());
    }

    public function testUnknownVerificationCodeIsRejected(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/en/register/verify/' . str_repeat('a', 64));

        // Assert
        static::assertResponseIsSuccessful();
        static::assertStringContainsString('Something went wrong', $crawler->filter('h1')->text());
    }

    public function testExpiredVerificationCodeIsRejected(): void
    {
        // Arrange
        $client = static::createClient();
        $code = $this->register($client);
        $em = $this->em($client);
        $user = $this->findUser($client, self::NEW_EMAIL);
        static::assertInstanceOf(User::class, $user);
        $user->setRegcodeExpiresAt(new DateTimeImmutable('-1 hour'));
        $em->flush();

        // Act
        $client->request('GET', '/en/register/verify/' . $code);

        // Assert
        static::assertResponseIsSuccessful();
        $user = $this->findUser($client, self::NEW_EMAIL);
        static::assertInstanceOf(User::class, $user);
        static::assertSame(UserStatus::Registered, $user->getStatus(), 'Expired code must not advance the status');
    }

    public function testAwaitingApprovalAccountSeesItsOwnMessageOnLogin(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $this->attemptLogin($client, self::PENDING_APPROVAL_EMAIL, self::FIXTURE_PASSWORD);

        // Assert
        $notification = $crawler->filter('.notification.is-danger')->text();
        static::assertStringContainsString('administrator', $notification);
        static::assertStringNotContainsString('not jet active', $notification);
    }

    public function testAccountStatusIsNotDisclosedBeforeThePasswordIsVerified(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $pending = $this->attemptLogin($client, self::PENDING_APPROVAL_EMAIL, 'wrong-password');
        $unknown = $this->attemptLogin($client, 'nobody@example.invalid', 'wrong-password');

        // Assert
        static::assertSame(
            $unknown->filter('.notification.is-danger')->text(),
            $pending->filter('.notification.is-danger')->text(),
            'A wrong password must look identical whether or not the account is active',
        );
    }

    public function testMalformedLoginPostRedirectsInsteadOfRaising(): void
    {
        // Arrange
        $client = static::createClient();
        $client->catchExceptions(false);

        // Act
        $client->request('POST', self::LOGIN_PATH);

        // Assert
        static::assertResponseRedirects();
        static::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    private function registrationForm(Crawler $crawler, array $overrides = []): Form
    {
        $values = array_merge([
            'registration[name]' => 'Brand New',
            'registration[email]' => self::NEW_EMAIL,
            'registration[plainPassword]' => self::NEW_PASSWORD,
            'registration[agreeTerms]' => true,
        ], $overrides);

        $form = $crawler->selectButton('Register')->form();
        foreach ($values as $field => $value) {
            $form[$field] = $value;
        }

        return $form;
    }

    private function register(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', self::REGISTER_PATH);
        $client->submit($this->registrationForm($crawler));

        $user = $this->findUser($client, self::NEW_EMAIL);
        static::assertInstanceOf(User::class, $user);

        return (string) $user->getRegcode();
    }

    private function attemptLogin(KernelBrowser $client, string $email, #[\SensitiveParameter] string $password): Crawler
    {
        $crawler = $client->request('GET', self::LOGIN_PATH);
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => $email,
                '_password' => $password,
            ]);
        $client->submit($form);

        return $client->followRedirect();
    }

    private function findUser(KernelBrowser $client, string $email): ?User
    {
        $em = $this->em($client);
        $em->clear();

        return $em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
