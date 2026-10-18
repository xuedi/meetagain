<?php declare(strict_types=1);

namespace Module\Task\Internal;

use InvalidArgumentException;
use Override;
use SensitiveParameter;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @implements TransportFactoryInterface<TransportInterface>
 */
readonly class StreamTransportFactory implements TransportFactoryInterface
{
    public const string SCHEME = 'task-stream:';
    public const string STREAM = 'tasks';
    public const string GROUP = 'workers';

    #[Override]
    public function createTransport(#[SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new RedisTransportFactory()->createTransport($this->toRedisDsn(substr($dsn, strlen(self::SCHEME))), $options, $serializer);
    }

    #[Override]
    public function supports(#[SensitiveParameter] string $dsn, array $options): bool
    {
        return str_starts_with($dsn, self::SCHEME);
    }

    public function toRedisDsn(#[SensitiveParameter] string $redisUrl): string
    {
        $parts = parse_url($redisUrl);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('REDIS_URL is not a Redis URL the task transport can use.');
        }

        $auth = '';
        if (isset($parts['user']) || isset($parts['pass'])) {
            $auth = ($parts['user'] ?? '') . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@';
        }
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        parse_str($parts['query'] ?? '', $query);
        $dbIndex = trim($parts['path'] ?? '', '/');
        if ($dbIndex !== '') {
            $query['dbindex'] = $dbIndex;
        }

        return sprintf(
            '%s://%s%s%s/%s/%s/%s%s',
            $parts['scheme'],
            $auth,
            $parts['host'],
            $port,
            self::STREAM,
            self::GROUP,
            rawurlencode((string) gethostname()),
            $query === [] ? '' : '?' . http_build_query($query),
        );
    }
}
