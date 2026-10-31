<?php declare(strict_types=1);

namespace Module\Geo\Tests\Unit\Internal\Import;

use Module\Geo\Internal\Import\Files;
use Module\Geo\Internal\Import\Options;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use ZipArchive;

final class FilesTest extends TestCase
{
    private const string SAMPLE = __DIR__ . '/../../../Sample';
    private const string BASE_URL = 'https://geonames.test/dump/';

    public function testALocalDumpSkipsCommentsAndBlankLines(): void
    {
        // Arrange
        $files = new Files(new MockHttpClient());

        // Act
        $rows = iterator_to_array($this->rowsOf($files, new Options(sourceDir: self::SAMPLE), 'countryInfo.txt'), false);

        // Assert
        self::assertSame(['DE', 'US', 'CN'], array_column($rows, 0));
    }

    public function testALocalTextFileStandsInForAZip(): void
    {
        // Arrange
        $files = new Files(new MockHttpClient());

        // Act
        $rows = iterator_to_array($this->rowsOf($files, new Options(sourceDir: self::SAMPLE), 'alternatenames/US.zip'), false);

        // Assert
        self::assertSame('United States', $rows[0][3]);
    }

    public function testAMissingOptionalFileYieldsNothing(): void
    {
        // Arrange
        $files = new Files(new MockHttpClient());

        // Act
        $rows = iterator_to_array($files->rows(new Options(sourceDir: self::SAMPLE), 'alternatenames/FR.zip', optional: true), false);

        // Assert
        self::assertSame([], $rows);
    }

    public function testAMissingRequiredFileFails(): void
    {
        // Arrange
        $files = new Files(new MockHttpClient());

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        iterator_to_array($files->rows(new Options(sourceDir: self::SAMPLE), 'cities500.zip'));
    }

    public function testADownloadedZipIsReadAndDeleted(): void
    {
        // Arrange
        $requested = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requested): MockResponse {
            $requested[] = $url;

            return new MockResponse(self::zipOf('DE.txt', "1\t2950159\tde\tBerlin\t1\t\t\t\t\t\n"));
        });
        $files = new Files($client);
        $before = glob(sys_get_temp_dir() . '/geonames-*') ?: [];

        // Act
        $rows = iterator_to_array($files->rows(new Options(baseUrl: self::BASE_URL), 'alternatenames/DE.zip'), false);

        // Assert
        self::assertSame([self::BASE_URL . 'alternatenames/DE.zip'], $requested);
        self::assertSame('Berlin', $rows[0][3]);
        self::assertSame($before, glob(sys_get_temp_dir() . '/geonames-*') ?: []);
    }

    public function testADownloadThatIsNotFoundIsOptional(): void
    {
        // Arrange
        $files = new Files(new MockHttpClient(new MockResponse('', ['http_code' => 404])));

        // Act
        $rows = iterator_to_array($files->rows(new Options(baseUrl: self::BASE_URL), 'alternatenames/AQ.zip', optional: true), false);

        // Assert
        self::assertSame([], $rows);
    }

    public function testAFailedDownloadFails(): void
    {
        // Arrange
        $files = new Files(new MockHttpClient(new MockResponse('', ['http_code' => 503])));

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        iterator_to_array($files->rows(new Options(baseUrl: self::BASE_URL), 'countryInfo.txt'));
    }

    /**
     * @return iterable<int, list<string>>
     */
    private function rowsOf(Files $files, Options $options, string $path): iterable
    {
        return $files->rows($options, $path);
    }

    private static function zipOf(string $entry, string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'geo-test-');
        $zip = new ZipArchive();
        $zip->open((string) $path, ZipArchive::OVERWRITE);
        $zip->addFromString($entry, $content);
        $zip->close();
        $bytes = (string) file_get_contents((string) $path);
        unlink((string) $path);

        return $bytes;
    }
}
