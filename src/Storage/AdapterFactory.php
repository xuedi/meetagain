<?php declare(strict_types=1);

namespace App\Storage;

use AsyncAws\S3\S3Client;
use InvalidArgumentException;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class AdapterFactory
{
    public const string IMAGES = 'images';
    public const string THUMBNAILS = 'thumbnails';
    public const string PRIVATE = 'private';

    /** @var array<string, string> */
    private const array LOCAL_DIRECTORIES = [
        self::IMAGES => '/data/images',
        self::THUMBNAILS => '/public/images/thumbnails',
        self::PRIVATE => '/data',
    ];

    public function __construct(
        #[Autowire('%app.storage.dsn%')]
        private string $dsn,
        #[Autowire('%app.storage.local_root%')]
        private string $localRoot,
    ) {}

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys(self::LOCAL_DIRECTORIES);
    }

    public function isRemote(): bool
    {
        return $this->dsn !== '';
    }

    public function create(string $name): FilesystemOperator
    {
        return $this->isRemote() ? $this->createRemote($name) : $this->createLocal($name);
    }

    public function createLocal(string $name): FilesystemOperator
    {
        return new Filesystem(new LocalFilesystemAdapter($this->localRoot . $this->localDirectory($name)));
    }

    public function describe(string $name): string
    {
        return $this->isRemote() ? 's3://' . $this->bucket($name) : $this->localRoot . $this->localDirectory($name);
    }

    private function createRemote(string $name): FilesystemOperator
    {
        $parts = parse_url($this->dsn);
        if ($parts === false || ($parts['scheme'] ?? '') !== 's3' || !isset($parts['host'], $parts['user'], $parts['pass'])) {
            throw new InvalidArgumentException('STORAGE_DSN must look like s3://<key>:<secret>@<host>[:<port>]/<bucket-prefix>.');
        }
        parse_str($parts['query'] ?? '', $query);
        $tls = ($query['tls'] ?? 'true') !== 'false';

        $client = new S3Client([
            'endpoint' => sprintf('%s://%s%s', $tls ? 'https' : 'http', $parts['host'], isset($parts['port']) ? ':' . $parts['port'] : ''),
            'region' => is_string($query['region'] ?? null) ? $query['region'] : 'us-east-1',
            'accessKeyId' => urldecode($parts['user']),
            'accessKeySecret' => urldecode($parts['pass']),
            'pathStyleEndpoint' => 'true',
            'sendChunkedBody' => 'false',
        ]);

        return new Filesystem(new AsyncAwsS3Adapter($client, $this->bucket($name)));
    }

    private function bucket(string $name): string
    {
        $this->localDirectory($name);
        $prefix = trim((string) parse_url($this->dsn, PHP_URL_PATH), '/');
        if ($prefix === '') {
            throw new InvalidArgumentException('STORAGE_DSN needs a bucket prefix as its path, for example s3://key:secret@garage:3900/meetagain.');
        }

        return $prefix . '-' . $name;
    }

    private function localDirectory(string $name): string
    {
        return self::LOCAL_DIRECTORIES[$name] ?? throw new InvalidArgumentException(sprintf('Unknown storage "%s".', $name));
    }
}
