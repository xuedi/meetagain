<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MemberControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';
    private const string REGULAR_EMAIL = 'Adem.Lane@example.org';
    private const string REGULAR_PASSWORD = '1234';

    public function testEditPageRendersReadOnlySummary(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $target = $this->getUserByEmail($client, 'Adem.Lane@example.org');

        // Act
        $crawler = $client->request('GET', '/en/admin/member/edit/' . $target->getId());

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(0, $crawler->filter('input[name="user[name]"]')->count(), 'Name field should not be editable');
        static::assertSame(0, $crawler->filter('textarea[name="user[bio]"]')->count(), 'Bio field should not be editable');
        static::assertGreaterThan(0, $crawler->filter('form[action*="set-role"]')->count(), 'Role action form should exist');
    }

    public function testSetRolePromotesUserToAdmin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $target = $this->getUserByEmail($client, 'Adem.Lane@example.org');
        $token = $this->extractCsrf($client, $target->getId(), '/set-role/admin');

        // Act
        $client->request('POST', '/en/admin/member/' . $target->getId() . '/set-role/admin', [
            '_token' => $token,
        ]);

        // Assert
        $this->assertResponseRedirects();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($target->getId());
        static::assertSame(UserRole::Admin, $reloaded->getRole());

        // Reset
        $reloaded->setRole(UserRole::User);
        $em->flush();
    }

    public function testSetRoleRejectsSelfModification(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $admin = $this->getUserByEmail($client, self::ADMIN_EMAIL);
        // Self-modification guard runs before CSRF check, so any token suffices
        $client->request('GET', '/en/admin/member/edit/' . $admin->getId());

        // Act
        $client->request('POST', '/en/admin/member/' . $admin->getId() . '/set-role/user', [
            '_token' => 'irrelevant',
        ]);

        // Assert
        $this->assertResponseRedirects();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($admin->getId());
        static::assertSame(UserRole::Admin, $reloaded->getRole(), 'Admin must not have demoted itself');
    }

    public function testSetRoleRejectsInvalidCsrf(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $target = $this->getUserByEmail($client, 'Adem.Lane@example.org');

        // Act
        $client->request('POST', '/en/admin/member/' . $target->getId() . '/set-role/admin', [
            '_token' => 'invalid-token',
        ]);

        // Assert
        $this->assertResponseRedirects();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($target->getId());
        static::assertSame(UserRole::User, $reloaded->getRole(), 'Role must not change on invalid CSRF');
    }

    public function testToggleVerifiedFlipsFlag(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $target = $this->getUserByEmail($client, 'Adem.Lane@example.org');
        $original = $target->isVerified();

        $token = $this->extractCsrf($client, $target->getId(), '/toggle/verified');

        // Act
        $client->request('POST', '/en/admin/member/' . $target->getId() . '/toggle/verified', [
            '_token' => $token,
        ]);

        // Assert
        $this->assertResponseRedirects();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($target->getId());
        static::assertNotSame($original, $reloaded->isVerified());

        // Reset
        $reloaded->setVerified($original);
        $em->flush();
    }

    public function testToggleFlagRejectsUnknownFlag(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $target = $this->getUserByEmail($client, 'Adem.Lane@example.org');
        $token = $this->extractCsrf($client, $target->getId(), '/toggle/verified');

        // Act
        $client->request('POST', '/en/admin/member/' . $target->getId() . '/toggle/foobar', [
            '_token' => $token,
        ]);

        // Assert
        static::assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testSetStatusRejectsInvalidTransition(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $target = $this->getUserByEmail($client, 'Adem.Lane@example.org');
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        if ($target->getStatus() !== UserStatus::Active) {
            $target->setStatus(UserStatus::Active);
            $em->flush();
        }
        $token = $this->extractCsrf($client, $target->getId(), '/set-status/');

        // Act: Active -> Denied is not in the allow-list
        $client->request('POST', '/en/admin/member/' . $target->getId() . '/set-status/' . UserStatus::Denied->value, [
            '_token' => $token,
        ]);

        // Assert
        $this->assertResponseRedirects();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($target->getId());
        static::assertSame(UserStatus::Active, $reloaded->getStatus(), 'Status must not change on disallowed transition');
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        $this->login($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
    }

    private function login(KernelBrowser $client, string $email, #[\SensitiveParameter] string $password): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => $email,
                '_password' => $password,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }

    private function getUserByEmail(KernelBrowser $client, string $email): User
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        static::assertNotNull($user, "User {$email} should exist");

        return $user;
    }

    private function extractCsrf(KernelBrowser $client, int $userId, string $actionFragment): string
    {
        $crawler = $client->request('GET', '/en/admin/member/edit/' . $userId);
        $this->assertResponseIsSuccessful();

        $forms = $crawler->filter('form')->reduce(static function ($form) use ($actionFragment): bool {
            $action = (string) $form->attr('action');
            return str_contains($action, $actionFragment);
        });
        static::assertGreaterThan(0, $forms->count(), "Form with action containing '{$actionFragment}' should exist");

        $token = $forms->first()->filter('input[name="_token"]')->attr('value');
        static::assertNotEmpty($token, 'CSRF token should be present in the form');

        return (string) $token;
    }
}
