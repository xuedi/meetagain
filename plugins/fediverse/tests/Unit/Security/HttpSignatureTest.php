<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\Security;

use App\Service\Security\SecretBox;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Security\HttpSignature;
use Symfony\Component\Clock\MockClock;

class HttpSignatureTest extends TestCase
{
    private const string DRAFT_PUBLIC_KEY = <<<'PEM'
        -----BEGIN PUBLIC KEY-----
        MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDCFENGw33yGihy92pDjZQhl0C3
        6rPJj+CvfSC8+q28hxA161QFNUd13wuCTUcq0Qd2qsBe/2hFyc2DCJJg0h1L78+6
        Z4UMR7EOcpfdUE9Hf3m/hs+FUR45uBJeDK1HSFHD8bHKD6kv8FPGfJTotc+2xjJw
        oYi+1hqp1fIekaxsyQIDAQAB
        -----END PUBLIC KEY-----
        PEM;
    private const string DRAFT_DATE = 'Sun, 05 Jan 2014 21:31:40 GMT';
    private const string DRAFT_TARGET = '/foo?param=value&pet=dog';
    private const string DRAFT_BODY = '{"hello": "world"}';
    private const string DRAFT_BASIC_SIGNATURE = 'qdx+H7PHHDZgy4y/Ahn9Tny9V3GP6YgBPyUXMmoxWtLbHpUnXS2mg2+SbrQDMCJypxBLSPQR2aAjn7ndmw2iicw3HMbe8VfEdKFYRqzic+efkb3nndiv/x1xSHDJWeSWkx3ButlYSuBskLu6kd9Fswtemr3lgdDEmn04swr2Os0=';
    private const string DRAFT_ALL_HEADERS_SIGNATURE = 'vSdrb+dS3EceC9bcwHSo4MlyKS59iFIrhgYkz8+oVLEEzmYZZvRs8rgOp+63LEM3v+MFHB32NfpB2bEKBIvB1q52LaEUHFv120V01IL+TAD48XaERZFukWgHoBTLMhYS2Gb51gWxpeIq8knRmPnYePbF5MOkR0Zkly4zKH7s1dE=';

    private SecretBox $secretBox;

    protected function setUp(): void
    {
        $this->secretBox = new SecretBox(base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    }

    public function testTheDraftsBasicTestVectorVerifies(): void
    {
        // Arrange
        $signature = $this->draftSignature('(request-target) host date', self::DRAFT_BASIC_SIGNATURE);

        // Act
        $valid = $this->httpSignature(self::DRAFT_DATE)->verify($signature, 'POST', self::DRAFT_TARGET, $this->draftHeaders(), '', self::DRAFT_PUBLIC_KEY);

        // Assert
        static::assertTrue($valid);
    }

    public function testTheDraftsAllHeadersTestVectorVerifiesWithItsBody(): void
    {
        // Act
        $valid = $this->verifyAllHeaders(self::DRAFT_DATE, self::DRAFT_BODY);

        // Assert
        static::assertTrue($valid);
    }

    public function testATamperedBodyIsRefused(): void
    {
        // Act
        $valid = $this->verifyAllHeaders(self::DRAFT_DATE, '{"hello": "World"}');

        // Assert
        static::assertFalse($valid);
    }

    public function testAStaleDateIsRefused(): void
    {
        // Act
        $valid = $this->verifyAllHeaders('Mon, 06 Jan 2014 09:31:41 GMT', self::DRAFT_BODY);

        // Assert
        static::assertFalse($valid);
    }

    public function testADateTooFarAheadIsRefused(): void
    {
        // Act
        $valid = $this->verifyAllHeaders('Sun, 05 Jan 2014 20:31:39 GMT', self::DRAFT_BODY);

        // Assert
        static::assertFalse($valid);
    }

    public function testABodyWithoutASignedDigestIsRefused(): void
    {
        // Arrange
        $signature = $this->draftSignature('(request-target) host date', self::DRAFT_BASIC_SIGNATURE);

        // Act
        $valid = $this->httpSignature(self::DRAFT_DATE)->verify(
            $signature,
            'POST',
            self::DRAFT_TARGET,
            $this->draftHeaders(),
            self::DRAFT_BODY,
            self::DRAFT_PUBLIC_KEY,
        );

        // Assert
        static::assertFalse($valid);
    }

    public function testASignatureOverTooFewHeadersIsRefused(): void
    {
        // Arrange
        $signature = $this->draftSignature('date', self::DRAFT_BASIC_SIGNATURE);

        // Act
        $valid = $this->httpSignature(self::DRAFT_DATE)->verify($signature, 'POST', self::DRAFT_TARGET, $this->draftHeaders(), '', self::DRAFT_PUBLIC_KEY);

        // Assert
        static::assertFalse($valid);
    }

    public function testTheSignerSignsTheDraftsSigningStringAndItsOwnSignatureVerifies(): void
    {
        // Arrange
        [$account, $publicKey] = $this->account();
        $httpSignature = $this->httpSignature(self::DRAFT_DATE);

        // Act
        $headers = $httpSignature->sign($account, 'POST', 'https://example.com' . self::DRAFT_TARGET, self::DRAFT_BODY);

        // Assert
        $signature = $httpSignature->parse($headers['signature']);
        static::assertNotNull($signature);
        static::assertSame('https://events.example/fediverse/@events#main-key', $signature['keyId']);
        $signingString = implode("\n", [
            '(request-target): post ' . self::DRAFT_TARGET,
            'host: example.com',
            'date: ' . self::DRAFT_DATE,
            'digest: SHA-256=X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE=',
            'content-type: application/activity+json',
        ]);
        static::assertSame(1, openssl_verify($signingString, $signature['signature'], $publicKey, OPENSSL_ALGO_SHA256));
        static::assertTrue($httpSignature->verify($signature, 'POST', self::DRAFT_TARGET, $headers, self::DRAFT_BODY, $publicKey));
    }

    public function testAnUnsignedHeaderIsNoSignature(): void
    {
        // Act + Assert
        static::assertNull($this->httpSignature(self::DRAFT_DATE)->parse(
            'keyId="https://social.example/users/a#main-key",headers="(request-target) host date"',
        ));
    }

    /** @return Generator<string, array{string, string, string}> */
    public static function elsewhere(): Generator
    {
        yield 'another path' => ['POST', '/bar?param=value&pet=dog', 'example.com'];
        yield 'another method' => ['PUT', self::DRAFT_TARGET, 'example.com'];
        yield 'another host' => ['POST', self::DRAFT_TARGET, 'evil.example'];
    }

    #[DataProvider('elsewhere')]
    public function testASignatureCannotBeReplayedElsewhere(string $method, string $target, string $host): void
    {
        // Arrange
        $signature = $this->draftSignature('(request-target) host date', self::DRAFT_BASIC_SIGNATURE);

        // Act
        $valid = $this->httpSignature(self::DRAFT_DATE)->verify(
            $signature,
            $method,
            $target,
            [...$this->draftHeaders(), 'host' => $host],
            '',
            self::DRAFT_PUBLIC_KEY,
        );

        // Assert
        static::assertFalse($valid);
    }

    public function testACorrectSignatureCheckedAgainstAnotherKeyIsRefused(): void
    {
        // Arrange
        $signature = $this->draftSignature('(request-target) host date', self::DRAFT_BASIC_SIGNATURE);
        [, $otherKey] = $this->account();

        // Act
        $valid = $this->httpSignature(self::DRAFT_DATE)->verify($signature, 'POST', self::DRAFT_TARGET, $this->draftHeaders(), '', $otherKey);

        // Assert
        static::assertFalse($valid);
    }

    /** @return Generator<string, array{string}> */
    public static function foreignAlgorithms(): Generator
    {
        yield 'rsa-sha1' => ['rsa-sha1'];
        yield 'hmac-sha256' => ['hmac-sha256'];
    }

    #[DataProvider('foreignAlgorithms')]
    public function testAnAlgorithmOutsideTheListIsRefused(string $algorithm): void
    {
        // Arrange
        $signature = $this->draftSignature('(request-target) host date', self::DRAFT_BASIC_SIGNATURE, $algorithm);

        // Act
        $valid = $this->httpSignature(self::DRAFT_DATE)->verify($signature, 'POST', self::DRAFT_TARGET, $this->draftHeaders(), '', self::DRAFT_PUBLIC_KEY);

        // Assert
        static::assertFalse($valid);
    }

    public function testHs2019WithAnRsaKeyVerifies(): void
    {
        // Arrange
        $signature = $this->draftSignature('(request-target) host date', self::DRAFT_BASIC_SIGNATURE, 'hs2019');

        // Act
        $valid = $this->httpSignature(self::DRAFT_DATE)->verify($signature, 'POST', self::DRAFT_TARGET, $this->draftHeaders(), '', self::DRAFT_PUBLIC_KEY);

        // Assert
        static::assertTrue($valid);
    }

    public function testADateThatIsNoHttpDateIsRefused(): void
    {
        // Arrange
        $headers = [...$this->draftHeaders(), 'date' => '2014-01-05T21:31:40Z'];
        [$signature, $publicKey] = $this->selfSigned($headers, '');

        // Act
        $valid = $this->httpSignature(self::DRAFT_DATE)->verify($signature, 'POST', self::DRAFT_TARGET, $headers, '', $publicKey);

        // Assert
        static::assertFalse($valid);
    }

    public function testADigestOfferingOnlySha512IsRefused(): void
    {
        // Arrange
        $headers = [...$this->draftHeaders(), 'digest' => 'SHA-512=' . base64_encode(hash('sha512', self::DRAFT_BODY, true))];
        [$signature, $publicKey] = $this->selfSigned($headers, ' digest');

        // Act
        $valid = $this->httpSignature(self::DRAFT_DATE)->verify($signature, 'POST', self::DRAFT_TARGET, $headers, self::DRAFT_BODY, $publicKey);

        // Assert
        static::assertFalse($valid);
    }

    public function testASelfSignedRequestVerifies(): void
    {
        // Arrange
        $headers = [...$this->draftHeaders(), 'digest' => 'SHA-256=' . base64_encode(hash('sha256', self::DRAFT_BODY, true))];
        [$signature, $publicKey] = $this->selfSigned($headers, ' digest');

        // Act
        $valid = $this->httpSignature(self::DRAFT_DATE)->verify($signature, 'POST', self::DRAFT_TARGET, $headers, self::DRAFT_BODY, $publicKey);

        // Assert
        static::assertTrue($valid, 'the helper the two tests above rely on signs correctly');
    }

    public function testAKeyThatIsNoPemIsRefused(): void
    {
        // Arrange
        $signature = $this->draftSignature('(request-target) host date', self::DRAFT_BASIC_SIGNATURE);

        // Act
        $valid = $this->httpSignature(self::DRAFT_DATE)->verify($signature, 'POST', self::DRAFT_TARGET, $this->draftHeaders(), '', 'not a key');

        // Assert
        static::assertFalse($valid);
    }

    public function testASignatureThatIsNoStrictBase64IsNoSignature(): void
    {
        // Act
        $parsed = $this->httpSignature(self::DRAFT_DATE)->parse('keyId="Test",headers="(request-target) host date",signature="not*base64!"');

        // Assert
        static::assertNull($parsed);
    }

    public function testTheFirstOfDuplicatedParametersWins(): void
    {
        // Act
        $parsed = $this->httpSignature(self::DRAFT_DATE)->parse(
            'keyId="https://social.example/users/a#main-key",headers="(request-target) host date",signature="c2ln",'
            . 'keyId="https://evil.example/users/m#main-key",headers="date"',
        );

        // Assert
        static::assertNotNull($parsed);
        static::assertSame('https://social.example/users/a#main-key', $parsed['keyId']);
        static::assertSame(['(request-target)', 'host', 'date'], $parsed['headers']);
    }

    /**
     * @param array<string, string> $headers
     * @return array{array{keyId: string, algorithm: ?string, headers: list<string>, signature: string}, string}
     */
    private function selfSigned(array $headers, string $extraNames): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($key);
        $lines = ['(request-target): post ' . self::DRAFT_TARGET, 'host: ' . $headers['host'], 'date: ' . $headers['date']];
        if ($extraNames !== '') {
            $lines[] = 'digest: ' . $headers['digest'];
        }
        $raw = '';
        openssl_sign(implode("\n", $lines), $raw, $key, OPENSSL_ALGO_SHA256);
        $parsed = $this->httpSignature(self::DRAFT_DATE)->parse(sprintf(
            'keyId="Test",algorithm="rsa-sha256",headers="(request-target) host date%s",signature="%s"',
            $extraNames,
            base64_encode((string) $raw),
        ));
        static::assertNotNull($parsed);

        return [$parsed, openssl_pkey_get_details($key)['key']];
    }

    private function verifyAllHeaders(string $date, string $body): bool
    {
        $signature = $this->draftSignature('(request-target) host date content-type digest content-length', self::DRAFT_ALL_HEADERS_SIGNATURE);
        $headers = [
            ...$this->draftHeaders(),
            'content-type' => 'application/json',
            'digest' => 'SHA-256=X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE=',
            'content-length' => '18',
        ];

        return $this->httpSignature($date)->verify($signature, 'POST', self::DRAFT_TARGET, $headers, $body, self::DRAFT_PUBLIC_KEY);
    }

    /**
     * @return array{keyId: string, algorithm: ?string, headers: list<string>, signature: string}
     */
    private function draftSignature(string $headers, string $signature, string $algorithm = 'rsa-sha256'): array
    {
        $parsed = $this->httpSignature(self::DRAFT_DATE)->parse(sprintf(
            'keyId="Test",algorithm="%s",headers="%s",signature="%s"',
            $algorithm,
            $headers,
            $signature,
        ));
        static::assertNotNull($parsed);

        return $parsed;
    }

    /** @return array<string, string> */
    private function draftHeaders(): array
    {
        return ['host' => 'example.com', 'date' => self::DRAFT_DATE];
    }

    private function httpSignature(string $now): HttpSignature
    {
        $actor = $this->createStub(Actor::class);
        $actor->method('keyId')->willReturn('https://events.example/fediverse/@events#main-key');

        return new HttpSignature($this->secretBox, $actor, new MockClock(new DateTimeImmutable($now)));
    }

    /** @return array{Account, string} */
    private function account(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($key);
        $privateKey = '';
        openssl_pkey_export($key, $privateKey);
        $publicKey = openssl_pkey_get_details($key)['key'];

        return [new Account(null, 'events', $publicKey, $this->secretBox->encrypt($privateKey), new DateTimeImmutable('2026-11-01')), $publicKey];
    }
}
