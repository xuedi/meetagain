<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Entity\SupportMessage;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\SupportChannel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SupportContactTest extends WebTestCase
{
    private const string MEMBER_EMAIL = 'Adem.Lane@example.org';
    private const string MEMBER_PASSWORD = '1234';

    public function testMemberSubmittingIsPointedAtTheInboxInsteadOfAThread(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::MEMBER_EMAIL, self::MEMBER_PASSWORD);

        // Act
        $crawler = $client->request('GET', '/en/contact');
        $form = $crawler
            ->selectButton('Send')
            ->form([
                'support_request[audience]' => 'organizer',
                'support_request[message]' => 'A functional-test support message.',
            ]);
        $crawler = $client->submit($form);

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString('message inbox', $client->getResponse()->getContent());
        static::assertSame(0, $crawler->filter('textarea[name="support_request[message]"]')->count(), 'The form should be replaced by the confirmation panel');

        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $member = $em->getRepository(User::class)->findOneBy(['email' => self::MEMBER_EMAIL]);
        $stored = $em->getRepository(SupportRequest::class)->findBy(['requester' => $member]);
        static::assertNotEmpty($stored);
        foreach ($stored as $request) {
            static::assertSame(SupportChannel::Message, $request->getChannel());
            static::assertNull($request->getToken(), 'A member request mints no thread token');
            static::assertNull($request->getEmail(), 'A member request stores no address of its own');
            $em->remove($request);
        }
        $em->flush();
    }

    public function testTheFormNeverAsksForAnAddress(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', '/en/contact');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(
            0,
            $crawler->filter('input[type="email"]')->count(),
            'The address is collected on the thread page, where posting it starts the double opt-in',
        );
    }

    public function testGuestSubmittingGetsAWorkingThreadUrl(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        // Act
        $crawler = $client->request('GET', '/en/contact');
        $form = $crawler
            ->selectButton('Send')
            ->form([
                'support_request[audience]' => 'admin',
                'support_request[message]' => 'My question has no return address.',
                'support_request[captcha]' => (string) $client->getRequest()->getSession()->get('captcha_text'),
            ]);
        $client->submit($form);

        // Assert
        $this->assertResponseRedirects();
        $threadUrl = (string) $client->getResponse()->headers->get('Location');
        static::assertMatchesRegularExpression('#/en/contact/request/[0-9a-f]{64}$#', $threadUrl);

        $token = substr($threadUrl, -64);
        $request = $em->getRepository(SupportRequest::class)->findOneBy(['token' => $token]);
        static::assertNotNull($request);
        static::assertNull($request->getEmail(), 'A fresh guest request carries no address at all');
        static::assertNull($request->getRequester(), 'A guest request points at no user');
        static::assertSame(SupportChannel::Thread, $request->getChannel());
        static::assertSame($token, $request->getToken());

        $messages = $em->getRepository(SupportMessage::class)->findBy(['supportRequest' => $request]);
        static::assertCount(1, $messages, 'The opening message is mirrored into the thread');

        $client->request('GET', (string) $threadUrl);
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString('My question has no return address.', $client->getResponse()->getContent());

        $em->remove($request);
        $em->flush();
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
}
