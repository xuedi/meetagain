<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Entity\SupportRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SupportContactTest extends WebTestCase
{
    private const string MEMBER_EMAIL = 'Adem.Lane@example.org';
    private const string MEMBER_PASSWORD = '1234';

    public function testSubmittingShowsConfirmationInsteadOfForm(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::MEMBER_EMAIL, self::MEMBER_PASSWORD);

        // Act
        $crawler = $client->request('GET', '/en/contact');
        $form = $crawler
            ->selectButton('Send')
            ->form([
                'support_request[message]' => 'A functional-test support message.',
            ]);
        $crawler = $client->submit($form);

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString('Thank you', $client->getResponse()->getContent());
        static::assertSame(0, $crawler->filter('textarea[name="support_request[message]"]')->count(), 'The form should be replaced by the confirmation panel');

        // Reset
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        foreach ($em->getRepository(SupportRequest::class)->findBy(['email' => self::MEMBER_EMAIL]) as $request) {
            $em->remove($request);
        }
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
