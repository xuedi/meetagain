<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Service\Security\SecretBox;
use App\Service\Security\SecretBoxException;
use PHPUnit\Framework\TestCase;

class SecretBoxTest extends TestCase
{
    private SecretBox $secretBox;

    protected function setUp(): void
    {
        // Arrange - a valid 32-byte key, base64-encoded
        $key = base64_encode(str_repeat('A', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $this->secretBox = new SecretBox($key);
    }

    public function testRoundTripReturnsCleartext(): void
    {
        // Arrange
        $cleartext = 'my-api-key-value';

        // Act
        $encoded = $this->secretBox->encrypt($cleartext);
        $decoded = $this->secretBox->decrypt($encoded);

        // Assert
        static::assertSame($cleartext, $decoded);
        static::assertNotSame($cleartext, $encoded);
    }

    public function testEncryptProducesDifferentCiphertextsForSamePlaintext(): void
    {
        // Arrange
        $cleartext = 'same-input';

        // Act
        $first = $this->secretBox->encrypt($cleartext);
        $second = $this->secretBox->encrypt($cleartext);

        // Assert - nonce randomness means ciphertexts differ
        static::assertNotSame($first, $second);
    }

    public function testDecryptThrowsOnTamperedCiphertext(): void
    {
        // Arrange
        $encoded = $this->secretBox->encrypt('original');
        $raw = base64_decode($encoded, strict: true);
        assert(is_string($raw));
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 0xFF);
        $tampered = base64_encode($raw);

        // Act / Assert
        $this->expectException(SecretBoxException::class);
        $this->secretBox->decrypt($tampered);
    }

    public function testDecryptThrowsWithWrongKey(): void
    {
        // Arrange
        $encoded = $this->secretBox->encrypt('secret');
        $wrongKey = base64_encode(str_repeat('B', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $other = new SecretBox($wrongKey);

        // Act / Assert
        $this->expectException(SecretBoxException::class);
        $other->decrypt($encoded);
    }

    public function testDecryptThrowsOnInvalidBase64(): void
    {
        // Act / Assert
        $this->expectException(SecretBoxException::class);
        $this->secretBox->decrypt('not!!valid_base64===');
    }

    public function testConstructorThrowsOnShortKey(): void
    {
        // Arrange
        $shortKey = base64_encode('short');

        // Act / Assert
        $this->expectException(SecretBoxException::class);
        new SecretBox($shortKey);
    }

    public function testConstructorThrowsOnInvalidBase64Key(): void
    {
        // Act / Assert
        $this->expectException(SecretBoxException::class);
        new SecretBox('not!!valid_base64===');
    }
}
