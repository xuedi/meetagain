<?php declare(strict_types=1);

namespace Plugin\Bookclub\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

readonly class OpenLibraryIsbnLookup implements IsbnLookupInterface
{
    private const API_URL = 'https://openlibrary.org/api/books';
    private const COVERS_URL = 'https://covers.openlibrary.org/b/isbn/';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {}

    public function lookup(string $isbn): ?BookData
    {
        $isbn = $this->normalizeIsbn($isbn);

        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => [
                    'bibkeys' => 'ISBN:' . $isbn,
                    'format' => 'json',
                    'jscmd' => 'data',
                ],
            ]);

            $data = $response->toArray();
            $key = 'ISBN:' . $isbn;

            if (!isset($data[$key])) {
                return null;
            }

            $bookInfo = $data[$key];

            return new BookData(
                isbn: $isbn,
                title: $bookInfo['title'] ?? '',
                author: $this->extractAuthors($bookInfo),
                description: $this->extractDescription($bookInfo),
                pageCount: $bookInfo['number_of_pages'] ?? null,
                publishedYear: $this->extractYear($bookInfo),
                coverUrl: $this->getCoverUrl($isbn),
            );
        } catch (Throwable $e) {
            $this->logger->error('ISBN lookup failed: ' . $e->getMessage(), [
                'isbn' => $isbn,
                'exception' => $e,
            ]);
            return null;
        }
    }

    private function normalizeIsbn(string $isbn): string
    {
        return preg_replace('/[^0-9X]/', '', strtoupper($isbn)) ?? $isbn;
    }

    private function extractAuthors(array $data): ?string
    {
        if (!isset($data['authors']) || !is_array($data['authors'])) {
            return null;
        }

        $names = array_filter(array_map(
            fn($author) => $author['name'] ?? null,
            $data['authors']
        ));

        return empty($names) ? null : implode(', ', $names);
    }

    private function extractDescription(array $data): ?string
    {
        if (isset($data['notes'])) {
            return is_string($data['notes']) ? $data['notes'] : ($data['notes']['value'] ?? null);
        }

        return null;
    }

    private function extractYear(array $data): ?int
    {
        if (!isset($data['publish_date'])) {
            return null;
        }

        if (preg_match('/\d{4}/', $data['publish_date'], $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    private function getCoverUrl(string $isbn): string
    {
        return self::COVERS_URL . $isbn . '-L.jpg';
    }
}
