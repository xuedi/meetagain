<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Comment\EventTargetProvider;
use App\Entity\Comment;
use App\Repository\CommentRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CommentSectionTest extends WebTestCase
{
    private const string OWNER_EMAIL = 'Crystal.Liu@example.org';
    private const string OTHER_EMAIL = 'Adem.Lane@example.org';
    private const string PASSWORD = '1234';
    private const string EVENT_URL = '/en/event/1';

    public function testMemberPostsCommentAndIsRedirectedBack(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::OWNER_EMAIL);

        // Act
        $this->submitComment($client, 'Looking forward to <b>this</b> <script>alert(1)</script>one!');

        // Assert
        $this->assertResponseRedirects(self::EVENT_URL);
        $crawler = $client->followRedirect();
        static::assertStringContainsString('Looking forward to this one!', $crawler->filter('body')->text());
        static::assertStringNotContainsString('alert(1)', (string) $client->getResponse()->getContent());
    }

    public function testOwnerDeletesOwnComment(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::OWNER_EMAIL);
        $this->submitComment($client, 'A comment to be removed again');
        $crawler = $client->followRedirect();
        $link = $crawler->filter('a[href*="/comment/event/1/delete/"]')->first();

        // Act
        $client->request('POST', (string) $link->attr('href'), ['_token' => (string) $link->attr('data-csrf-token')]);

        // Assert
        $this->assertResponseRedirects(self::EVENT_URL);
        $crawler = $client->followRedirect();
        static::assertStringNotContainsString('A comment to be removed again', $crawler->filter('body')->text());
    }

    public function testNonOwnerCannotDeleteSomeoneElsesComment(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::OTHER_EMAIL);
        $comments = static::getContainer()->get(CommentRepository::class)->findForTarget(EventTargetProvider::TYPE, 1);
        $foreign = array_filter($comments, static fn(Comment $c): bool => $c->getUser()?->getEmail() !== self::OTHER_EMAIL);
        $commentId = (int) reset($foreign)->getId();
        $this->primeCsrfToken($client, 'app_comment_delete' . $commentId);

        // Act
        $client->request('POST', '/en/comment/event/1/delete/' . $commentId, ['_token' => 'primed-token']);

        // Assert
        $this->assertResponseRedirects(self::EVENT_URL);
        static::assertNotNull(static::getContainer()->get(CommentRepository::class)->find($commentId));
    }

    public function testGuestSeesNoCommentForm(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $crawler = $client->request('GET', self::EVENT_URL);

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertCount(0, $crawler->filter('form[action*="/comment/"]'));
    }

    public function testUnknownTargetTypeIsNotFound(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::OWNER_EMAIL);
        $this->primeCsrfToken($client, 'app_comment_createkraken1');

        // Act
        $client->request('POST', '/en/comment/kraken/1', ['_token' => 'primed-token', 'content' => 'Hello']);

        // Assert
        $this->assertResponseStatusCodeSame(404);
    }

    public function testOverLongCommentIsRefused(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::OWNER_EMAIL);

        // Act
        $this->submitComment($client, str_repeat('x', 5001));

        // Assert
        $this->assertResponseRedirects(self::EVENT_URL);
        $crawler = $client->followRedirect();
        static::assertStringNotContainsString(str_repeat('x', 5001), $crawler->filter('body')->text());
    }

    private function submitComment(KernelBrowser $client, string $content): void
    {
        $crawler = $client->request('GET', self::EVENT_URL);
        $form = $crawler->filter('form[action*="/comment/event/1"]')->form();
        $form['content'] = $content;
        $client->submit($form);
    }

    private function primeCsrfToken(KernelBrowser $client, string $tokenId): void
    {
        $session = $client->getSession();
        $session->set('_csrf/' . $tokenId, 'primed-token');
        $session->save();
    }

    private function login(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => $email,
                '_password' => self::PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
