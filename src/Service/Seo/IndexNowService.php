<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use DateTimeImmutable;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class IndexNowService
{
    private const string CONFIG_KEY = 'indexnow_key';
    private const string CONFIG_LAST_SUBMITTED = 'indexnow_last_submitted';
    private const string INDEXNOW_ENDPOINT = 'https://api.indexnow.org/IndexNow';

    public function __construct(
        private ConfigService $configService,
        private HttpClientInterface $httpClient,
        private UrlGeneratorInterface $urlGenerator,
        private LanguageService $languageService,
        private EventRepository $eventRepository,
        private CmsRepository $cmsRepository,
    ) {}

    public function getOrCreateKey(): string
    {
        $key = $this->configService->getString(self::CONFIG_KEY, '');

        if ($key !== '') {
            return $key;
        }

        $key = bin2hex(random_bytes(16));
        $this->configService->setString(self::CONFIG_KEY, $key);

        return $key;
    }

    /**
     * @return array<string>
     */
    public function getUrlList(): array
    {
        $defaultLocale = $this->languageService->getFilteredDefaultLocale();

        $urls = [];

        $staticRoutes = [
            ['route' => 'app_default', 'params' => []],
            ['route' => 'app_event', 'params' => []],
            ['route' => 'app_member', 'params' => ['page' => 1]],
        ];

        foreach ($staticRoutes as $route) {
            $urls[] = $this->urlGenerator->generate(
                $route['route'],
                ['_locale' => $defaultLocale, ...$route['params']],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        foreach ($this->cmsRepository->findPublished() as $page) {
            $slug = $page->getSlug();
            if ($slug === null) {
                continue;
            }

            $urls[] = $this->urlGenerator->generate(
                'app_catch_all',
                ['_locale' => $defaultLocale, 'page' => $slug],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        foreach ($this->eventRepository->findForSitemap() as $event) {
            $id = $event->getId();
            if ($id === null) {
                continue;
            }

            $urls[] = $this->urlGenerator->generate(
                'app_event_details',
                ['_locale' => $defaultLocale, 'id' => $id],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        return $urls;
    }

    /**
     * @return array{status: int, host: string}
     */
    public function submit(): array
    {
        $key = $this->getOrCreateKey();
        $host = parse_url($this->configService->getHost(), PHP_URL_HOST) ?? $this->configService->getHost();

        $payload = [
            'host' => $host,
            'key' => $key,
            'keyLocation' => rtrim($this->configService->getHost(), '/') . '/' . $key . '.txt',
            'urlList' => $this->getUrlList(),
        ];

        $response = $this->httpClient->request('POST', self::INDEXNOW_ENDPOINT, [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'json' => $payload,
        ]);

        return [
            'status' => $response->getStatusCode(),
            'host' => $host,
        ];
    }

    public function getLastSubmittedAt(): ?DateTimeImmutable
    {
        $value = $this->configService->getString(self::CONFIG_LAST_SUBMITTED, '');

        if ($value === '') {
            return null;
        }

        $dt = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $value);

        return $dt !== false ? $dt : null;
    }

    public function recordSubmission(): void
    {
        $this->configService->setString(
            self::CONFIG_LAST_SUBMITTED,
            (new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM),
        );
    }
}
