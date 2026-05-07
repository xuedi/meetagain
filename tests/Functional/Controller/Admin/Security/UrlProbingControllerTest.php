<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Security;

use App\Entity\UrlProbingIncident;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UrlProbingControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testListPageRendersForAdmin(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/security/url-probing');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(3, $crawler->filter('.tabs.is-boxed li')->count(), 'Three security tabs should be rendered');
    }

    public function testListPageRedirectsGuestToLogin(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/en/admin/security/url-probing');

        // Assert
        static::assertTrue($client->getResponse()->isRedirect() || $client->getResponse()->getStatusCode() === 403);
    }

    public function testShowPageRendersIncidentMetadataAndDeepLink(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        $incident = new UrlProbingIncident();
        $incident->setIp('203.0.113.42');
        $incident->setStartedAt(new DateTimeImmutable('2026-05-01 10:00:00'));
        $incident->setEndedAt(new DateTimeImmutable('2026-05-01 10:30:00'));
        $incident->setProbeCount(35);
        $incident->setDistinctUrlCount(35);
        $incident->setUserAgent('curl/8.0');
        $incident->setSampleUrls(['/.env', '/.env.local', '/admin']);
        $incident->setCreatedAt(new DateTimeImmutable());
        $em->persist($incident);
        $em->flush();

        try {
            // Act
            $crawler = $client->request('GET', '/en/admin/security/url-probing/' . $incident->getId());

            // Assert
            $this->assertResponseIsSuccessful();
            $bodyText = $crawler->filter('body')->text();
            static::assertStringContainsString('203.0.113.42', $bodyText);
            static::assertStringContainsString('/.env', $bodyText);
            $deepLinkAttr = $crawler->filter('.level-right a')->extract(['href']);
            $matched = false;
            foreach ($deepLinkAttr as $href) {
                if (
                    !(
                        str_contains((string) $href, '/admin/logs/404')
                        && str_contains((string) $href, 'ip=203.0.113.42')
                    )
                ) {
                    continue;
                }

                $matched = true;
                break;
            }
            static::assertTrue($matched, 'Should render a "view raw 404s" deep-link with the IP query param');
        } finally {
            $em->clear();
            $reloaded = $em->getRepository(UrlProbingIncident::class)->find($incident->getId());
            if ($reloaded !== null) {
                $em->remove($reloaded);
                $em->flush();
            }
        }
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => self::ADMIN_EMAIL,
                '_password' => self::ADMIN_PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
