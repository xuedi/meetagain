<?php declare(strict_types=1);

namespace App\Service\Security;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class ChallengeSigner
{
    public const int MAX_AGE_MS = 7200000;

    public function __construct(
        #[\SensitiveParameter]
        #[Autowire('%kernel.secret%')]
        private string $secret,
        #[Autowire(service: 'cache.security_challenge')]
        private CacheInterface $cache,
        private ClockInterface $clock,
    ) {}

    public function issue(string $context, int $difficulty): string
    {
        $encoded = $this->encode([
            'n' => bin2hex(random_bytes(16)),
            't' => $this->nowMs(),
            'd' => $difficulty,
            'c' => $context,
        ]);

        return $encoded . '.' . $this->sign($encoded);
    }

    /**
     * @return array{nonce: string, issuedAt: int, difficulty: int}|null
     */
    public function verify(string $stamp, string $context): ?array
    {
        $parts = explode('.', $stamp, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;
        if (!hash_equals($this->sign($encoded), $signature)) {
            return null;
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload) || !isset($payload['n'], $payload['t'], $payload['d'], $payload['c'])) {
            return null;
        }

        if (!hash_equals((string) $payload['c'], $context)) {
            return null;
        }

        $issuedAt = (int) $payload['t'];
        if (($this->nowMs() - $issuedAt) > self::MAX_AGE_MS) {
            return null;
        }

        return [
            'nonce' => (string) $payload['n'],
            'issuedAt' => $issuedAt,
            'difficulty' => (int) $payload['d'],
        ];
    }

    public function burn(string $nonce): bool
    {
        $fresh = false;
        $this->cache->get('challenge_' . $nonce, static function (ItemInterface $item) use (&$fresh): bool {
            $item->expiresAfter((int) (self::MAX_AGE_MS / 1000));
            $fresh = true;

            return true;
        });

        return $fresh;
    }

    public function isProofValid(string $nonce, string $proof, int $difficulty): bool
    {
        return $this->leadingZeroBits(hash('sha256', $nonce . $proof, true)) >= $difficulty;
    }

    public function nowMs(): int
    {
        return (int) $this->clock->now()->format('Uv');
    }

    private function leadingZeroBits(string $binaryHash): int
    {
        $bits = 0;
        foreach (str_split($binaryHash) as $byte) {
            $value = ord($byte);
            if ($value === 0) {
                $bits += 8;
                continue;
            }

            for ($mask = 0x80; $mask > 0; $mask >>= 1) {
                if (($value & $mask) !== 0) {
                    return $bits;
                }
                $bits++;
            }
        }

        return $bits;
    }

    private function sign(string $encoded): string
    {
        return hash_hmac('sha256', $encoded, $this->secret);
    }

    /**
     * @param array<string, scalar> $payload
     */
    private function encode(array $payload): string
    {
        return rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');
    }
}
