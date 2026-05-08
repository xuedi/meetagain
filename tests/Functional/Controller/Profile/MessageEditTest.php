<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Profile;

use App\Entity\Message;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MessageEditTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string PARTNER_EMAIL = 'Crystal.Liu@example.org';

    public function testAnonymousIsRedirected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/en/profile/messages/1/edit');
        $this->assertResponseRedirects();
    }

    public function testWithinWindowSucceeds(): void
    {
        $client = static::createClient();
        [$em, $admin, $partner] = $this->bootContext($client);
        $msg = $this->seedMessage($em, $admin, $partner, '-2 minutes', 'orig content');

        $client->request('POST', '/en/profile/messages/' . $msg->getId() . '/edit', [
            '_token' => $this->csrfToken($client, $partner->getId()),
            'content' => 'updated content',
        ]);

        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame($msg->getId(), $payload['id']);
        self::assertSame('updated content', $payload['content']);
        self::assertNotNull($payload['editedAt']);

        $em->clear();
        $persisted = $em->find(Message::class, $msg->getId());
        self::assertSame('updated content', $persisted->getContent());
        self::assertNotNull($persisted->getEditedAt());
    }

    public function testNonSenderForbidden(): void
    {
        $client = static::createClient();
        [$em, $admin, $partner] = $this->bootContext($client);
        $msg = $this->seedMessage($em, $partner, $admin, '-2 minutes', 'partner says hi');

        $client->request('POST', '/en/profile/messages/' . $msg->getId() . '/edit', [
            '_token' => $this->csrfToken($client, $partner->getId()),
            'content' => 'hijack attempt',
        ]);

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testPastWindowForbidden(): void
    {
        $client = static::createClient();
        [$em, $admin, $partner] = $this->bootContext($client);
        $msg = $this->seedMessage($em, $admin, $partner, '-15 minutes', 'too old');

        $client->request('POST', '/en/profile/messages/' . $msg->getId() . '/edit', [
            '_token' => $this->csrfToken($client, $partner->getId()),
            'content' => 'changed',
        ]);

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testEmptyContentRejected(): void
    {
        $client = static::createClient();
        [$em, $admin, $partner] = $this->bootContext($client);
        $msg = $this->seedMessage($em, $admin, $partner, '-2 minutes', 'orig');

        $client->request('POST', '/en/profile/messages/' . $msg->getId() . '/edit', [
            '_token' => $this->csrfToken($client, $partner->getId()),
            'content' => '   ',
        ]);

        self::assertSame(400, $client->getResponse()->getStatusCode());
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('profile_messages.edit_empty', $payload['error']);
    }

    public function testNoChangeRejected(): void
    {
        $client = static::createClient();
        [$em, $admin, $partner] = $this->bootContext($client);
        $msg = $this->seedMessage($em, $admin, $partner, '-2 minutes', 'same content');

        $client->request('POST', '/en/profile/messages/' . $msg->getId() . '/edit', [
            '_token' => $this->csrfToken($client, $partner->getId()),
            'content' => 'same content',
        ]);

        self::assertSame(400, $client->getResponse()->getStatusCode());
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('profile_messages.edit_no_change', $payload['error']);
    }

    public function testTooLongRejected(): void
    {
        $client = static::createClient();
        [$em, $admin, $partner] = $this->bootContext($client);
        $msg = $this->seedMessage($em, $admin, $partner, '-2 minutes', 'orig');

        $client->request('POST', '/en/profile/messages/' . $msg->getId() . '/edit', [
            '_token' => $this->csrfToken($client, $partner->getId()),
            'content' => str_repeat('a', 5001),
        ]);

        self::assertSame(400, $client->getResponse()->getStatusCode());
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('profile_messages.edit_too_long', $payload['error']);
    }

    public function testDeletedMessageForbidden(): void
    {
        $client = static::createClient();
        [$em, $admin, $partner] = $this->bootContext($client);
        $msg = $this->seedMessage($em, $admin, $partner, '-2 minutes', 'orig', deleted: true);

        $client->request('POST', '/en/profile/messages/' . $msg->getId() . '/edit', [
            '_token' => $this->csrfToken($client, $partner->getId()),
            'content' => 'should not work',
        ]);

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testMissingCsrfRejected(): void
    {
        $client = static::createClient();
        [$em, $admin, $partner] = $this->bootContext($client);
        $msg = $this->seedMessage($em, $admin, $partner, '-2 minutes', 'orig');

        $client->request('POST', '/en/profile/messages/' . $msg->getId() . '/edit', [
            'content' => 'updated content',
        ]);

        self::assertSame(403, $client->getResponse()->getStatusCode());
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('profile_messages.edit_csrf', $payload['error']);
    }

    /**
     * @return array{0: EntityManagerInterface, 1: User, 2: User}
     */
    private function bootContext(KernelBrowser $client): array
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $userRepo = $em->getRepository(User::class);
        $admin = $userRepo->findOneBy(['email' => self::ADMIN_EMAIL]);
        $partner = $userRepo->findOneBy(['email' => self::PARTNER_EMAIL]);
        if (!$admin instanceof User || !$partner instanceof User) {
            self::fail('Required fixture users missing');
        }
        $client->loginUser($admin);

        return [$em, $admin, $partner];
    }

    private function seedMessage(
        EntityManagerInterface $em,
        User $sender,
        User $receiver,
        string $createdAtRelative,
        string $content,
        bool $deleted = false,
    ): Message {
        $msg = new Message();
        $msg->setSender($sender);
        $msg->setReceiver($receiver);
        $msg->setCreatedAt(new DateTimeImmutable($createdAtRelative));
        $msg->setContent($content);
        $msg->setDeleted($deleted);
        $msg->setWasRead(false);
        $em->persist($msg);
        $em->flush();

        return $msg;
    }

    private function csrfToken(KernelBrowser $client, int $partnerId): string
    {
        $crawler = $client->request('GET', '/en/profile/messages/' . $partnerId);
        $token = $crawler->filter('#message-form')->attr('data-csrf-token');
        if (!is_string($token) || $token === '') {
            self::fail('Could not extract CSRF token from messages page');
        }

        return $token;
    }
}
