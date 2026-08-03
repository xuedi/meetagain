<?php declare(strict_types=1);

namespace Tests\Unit\Publisher\WellKnown;

use App\Publisher\WellKnown\SecurityTxtProvider;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SecurityTxtProviderTest extends TestCase
{
    public function testConfiguredContactIsUsed(): void
    {
        // Arrange
        $provider = $this->provider(contact: 'mailto:security@example.org', encryption: '', languages: '');

        // Act
        $body = $provider->provide($this->request())->body ?? '';

        // Assert
        self::assertStringContainsString('Contact: mailto:security@example.org', $body);
    }

    public function testContactFallsBackToTheContactPage(): void
    {
        // Arrange
        $provider = $this->provider(contact: '', encryption: '', languages: '');

        // Act
        $body = $provider->provide($this->request())->body ?? '';

        // Assert
        self::assertStringContainsString('Contact: https://example.org/en/contact', $body);
    }

    public function testExpiresIsLessThanOneYearAhead(): void
    {
        // Arrange
        $provider = $this->provider(contact: 'mailto:security@example.org', encryption: '', languages: '');

        // Act
        $body = $provider->provide($this->request())->body ?? '';

        // Assert
        preg_match('/^Expires: (.+)$/m', $body, $matches);
        self::assertLessThanOrEqual(new DateTimeImmutable('+1 year +1 minute'), new DateTimeImmutable($matches[1] ?? '@0'));
    }

    public function testCanonicalPointsAtTheRequestHost(): void
    {
        // Arrange
        $provider = $this->provider(contact: 'mailto:security@example.org', encryption: '', languages: '');

        // Act
        $body = $provider->provide($this->request())->body ?? '';

        // Assert
        self::assertStringContainsString('Canonical: https://example.org/.well-known/security.txt', $body);
    }

    public function testOptionalFieldsAreOmittedWhenUnset(): void
    {
        // Arrange
        $provider = $this->provider(contact: 'mailto:security@example.org', encryption: '', languages: '', enabledCodes: []);

        // Act
        $body = $provider->provide($this->request())->body ?? '';

        // Assert
        self::assertStringNotContainsString('Encryption:', $body);
        self::assertStringNotContainsString('Preferred-Languages:', $body);
    }

    public function testPreferredLanguagesFallBackToEnabledCodes(): void
    {
        // Arrange
        $provider = $this->provider(contact: 'mailto:security@example.org', encryption: '', languages: '', enabledCodes: ['en', 'de']);

        // Act
        $body = $provider->provide($this->request())->body ?? '';

        // Assert
        self::assertStringContainsString('Preferred-Languages: en, de', $body);
    }

    /**
     * @param list<string> $enabledCodes
     */
    private function provider(string $contact, string $encryption, string $languages, array $enabledCodes = ['en']): SecurityTxtProvider
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getSecurityContact')->willReturn($contact);
        $configService->method('getSecurityEncryptionUrl')->willReturn($encryption);
        $configService->method('getSecurityPreferredLanguages')->willReturn($languages);

        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getEnabledCodes')->willReturn($enabledCodes);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.org/en/contact');

        return new SecurityTxtProvider($configService, $languageService, $urlGenerator);
    }

    private function request(): Request
    {
        return Request::create('https://example.org/.well-known/security.txt');
    }
}
