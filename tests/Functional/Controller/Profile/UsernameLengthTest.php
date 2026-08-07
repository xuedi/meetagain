<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Profile;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UsernameLengthTest extends WebTestCase
{
    private const string USER_EMAIL = 'Crystal.Liu@example.org';
    private const string USER_PASSWORD = '1234';
    private const string GRANDFATHERED_NAME = 'Maximiliane von Habsburg-Lothringen';

    public function testAGrandfatheredLongNameStillSaves(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client);
        $original = $this->renameTo($client, self::GRANDFATHERED_NAME);

        // Act
        $crawler = $client->request('GET', '/en/profile/');
        $client->submit($crawler->selectButton('Save')->form());

        // Assert
        $this->assertResponseRedirects();
        $this->assertSame(self::GRANDFATHERED_NAME, $this->currentName($client));
        $this->renameTo($client, $original);
    }

    public function testRenamingBeyondTheCapIsRejected(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client);
        $original = $this->renameTo($client, self::GRANDFATHERED_NAME);

        // Act
        $crawler = $client->request('GET', '/en/profile/');
        $form = $crawler->selectButton('Save')->form();
        $form['profile[name]'] = 'Wilhelmina Charlotte von Hohenzollern-Sigmaringen';
        $client->submit($form);

        // Assert
        $this->assertResponseIsUnprocessable();
        $this->assertSame(self::GRANDFATHERED_NAME, $this->currentName($client));
        $this->renameTo($client, $original);
    }

    public function testRenamingWithinTheCapSucceeds(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client);
        $original = $this->renameTo($client, self::GRANDFATHERED_NAME);

        // Act
        $crawler = $client->request('GET', '/en/profile/');
        $form = $crawler->selectButton('Save')->form();
        $form['profile[name]'] = 'Crystal L.';
        $client->submit($form);

        // Assert
        $this->assertResponseRedirects();
        $this->assertSame('Crystal L.', $this->currentName($client));
        $this->renameTo($client, $original);
    }

    private function login(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $client->submit($crawler->selectButton('Login')->form([
            '_username' => self::USER_EMAIL,
            '_password' => self::USER_PASSWORD,
        ]));
        $client->followRedirect();
    }

    private function renameTo(KernelBrowser $client, string $name): string
    {
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $user = $this->findUser($client);
        $previous = (string) $user->getName();
        $user->setName($name);
        $entityManager->flush();

        return $previous;
    }

    private function currentName(KernelBrowser $client): string
    {
        $client->getContainer()->get(EntityManagerInterface::class)->clear();

        return (string) $this->findUser($client)->getName();
    }

    private function findUser(KernelBrowser $client): User
    {
        $user = $client->getContainer()->get(UserRepository::class)->findOneBy(['email' => self::USER_EMAIL]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
