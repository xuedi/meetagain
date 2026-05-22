<?php declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reversible secret storage via libsodium secretbox.
 *
 * Wire format: base64(24-byte nonce || ciphertext).
 * Key is read from APP_SECRET_BOX_KEY (base64-encoded 32-byte value).
 * Rotate by re-encrypting every consumer's records.
 */
readonly class SecretBox
{
    private string $key;

    public function __construct(#[Autowire(env: 'APP_SECRET_BOX_KEY')] string $base64Key)
    {
        $decoded = base64_decode($base64Key, strict: true);

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new SecretBoxException(sprintf(
                'APP_SECRET_BOX_KEY must be a base64-encoded %d-byte value.',
                SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            ));
        }

        $this->key = $decoded;
    }

    public function encrypt(string $cleartext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($cleartext, $nonce, $this->key);

        return base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, strict: true);

        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new SecretBoxException('Invalid encoded value: base64 decode failed or value is too short.');
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cleartext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($cleartext === false) {
            throw new SecretBoxException('Decryption failed: ciphertext is corrupted or the key is wrong.');
        }

        return $cleartext;
    }
}
