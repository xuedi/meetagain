<?php declare(strict_types=1);

namespace Plugin\Fediverse\Security;

use App\Service\Security\SecretBox;
use DateTimeImmutable;
use DateTimeZone;
use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\Entity\Account;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;

readonly class HttpSignature
{
    public const int MAX_AGE_SECONDS = 43200;
    public const int MAX_SKEW_SECONDS = 3600;

    private const string HTTP_DATE = 'D, d M Y H:i:s \G\M\T';
    private const string REQUEST_TARGET = '(request-target)';
    private const array REQUIRED_HEADERS = [self::REQUEST_TARGET, 'host', 'date'];
    private const array ALGORITHMS = ['rsa-sha256', 'hs2019'];

    public function __construct(
        private SecretBox $secretBox,
        private Actor $actor,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{keyId: string, algorithm: ?string, headers: list<string>, signature: string}|null
     */
    public function parse(string $header): ?array
    {
        $matches = [];
        preg_match_all('/(\w+)="([^"]*)"/', $header, $matches, PREG_SET_ORDER);
        $params = [];
        foreach ($matches as $match) {
            $params[$match[1]] ??= $match[2];
        }

        $keyId = $params['keyId'] ?? '';
        $signature = base64_decode($params['signature'] ?? '', true);
        if ($keyId === '' || $signature === false || $signature === '') {
            return null;
        }

        return [
            'keyId' => $keyId,
            'algorithm' => isset($params['algorithm']) ? strtolower($params['algorithm']) : null,
            'headers' => array_values(array_filter(explode(' ', strtolower($params['headers'] ?? 'date')), static fn(string $name): bool => $name !== '')),
            'signature' => $signature,
        ];
    }

    /**
     * @return array<string, string> the headers to send, the signature among them
     */
    public function sign(Account $account, string $method, string $url, ?string $body): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);
        $query = parse_url($url, PHP_URL_QUERY);
        $target = (string) (parse_url($url, PHP_URL_PATH) ?? '/') . (is_string($query) ? '?' . $query : '');

        $headers = [
            'host' => is_int($port) ? $host . ':' . $port : $host,
            'date' => $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format(self::HTTP_DATE),
        ];
        if ($body !== null) {
            $headers['digest'] = $this->digest($body);
            $headers['content-type'] = 'application/activity+json';
        }

        $names = [self::REQUEST_TARGET, ...array_keys($headers)];
        $signature = null;
        $signed = openssl_sign(
            (string) $this->signingString($names, $method, $target, $headers),
            $signature,
            $this->secretBox->decrypt($account->getEncryptedPrivateKey()),
            OPENSSL_ALGO_SHA256,
        );
        if (!$signed || !is_string($signature)) {
            throw new RuntimeException('Fediverse: signing a request failed.');
        }

        $headers['signature'] = sprintf(
            'keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
            $this->actor->keyId($account),
            implode(' ', $names),
            base64_encode($signature),
        );

        return $headers;
    }

    /**
     * @param array{keyId: string, algorithm: ?string, headers: list<string>, signature: string} $signature
     * @param array<string, string> $headers the received headers, keyed by lowercase name
     */
    public function verify(array $signature, string $method, string $target, array $headers, string $body, string $publicKeyPem): bool
    {
        $names = $signature['headers'];
        if ($signature['algorithm'] !== null && !in_array($signature['algorithm'], self::ALGORITHMS, true)) {
            return false;
        }

        if (array_diff(self::REQUIRED_HEADERS, $names) !== [] || $body !== '' && !in_array('digest', $names, true)) {
            return false;
        }

        if (!$this->isFresh($headers['date'] ?? '') || isset($headers['digest']) && !$this->digestMatches($headers['digest'], $body)) {
            return false;
        }

        $signingString = $this->signingString($names, $method, $target, $headers);
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($signingString === null || $publicKey === false) {
            return false;
        }

        return openssl_verify($signingString, $signature['signature'], $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function digest(string $body): string
    {
        return 'SHA-256=' . base64_encode(hash('sha256', $body, true));
    }

    private function digestMatches(string $header, string $body): bool
    {
        foreach (explode(',', $header) as $entry) {
            $entry = trim($entry);
            if (stripos($entry, 'SHA-256=') === 0) {
                return hash_equals($this->digest($body), 'SHA-256=' . substr($entry, 8));
            }
        }

        return false;
    }

    private function isFresh(string $date): bool
    {
        $sent = DateTimeImmutable::createFromFormat(self::HTTP_DATE, $date, new DateTimeZone('UTC'));
        if ($sent === false) {
            return false;
        }

        $age = $this->clock->now()->getTimestamp() - $sent->getTimestamp();

        return $age <= self::MAX_AGE_SECONDS && $age >= -self::MAX_SKEW_SECONDS;
    }

    /**
     * @param list<string> $names
     * @param array<string, string> $headers
     */
    private function signingString(array $names, string $method, string $target, array $headers): ?string
    {
        $lines = [];
        foreach ($names as $name) {
            if ($name === self::REQUEST_TARGET) {
                $lines[] = $name . ': ' . strtolower($method) . ' ' . $target;
            } elseif (isset($headers[$name])) {
                $lines[] = $name . ': ' . $headers[$name];
            } else {
                return null;
            }
        }

        return implode("\n", $lines);
    }
}
