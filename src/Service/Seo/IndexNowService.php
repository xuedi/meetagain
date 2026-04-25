<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Sentry\Severity;
use Sentry\State\Scope;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Sentry\captureMessage;
use function Sentry\withScope;

readonly class IndexNowService
{
    private const string STATE_KEY = 'seo_indexnow_key';
    private const string STATE_KEY_LAST_SUBMIT = 'seo_indexnow_last_submit';
    private const string INDEXNOW_ENDPOINT = 'https://api.indexnow.org/IndexNow';

    public function __construct(
        private ConfigService $configService,
        private AppStateService $appStateService,
        private HttpClientInterface $httpClient,
        private UrlGeneratorInterface $urlGenerator,
        private LanguageService $languageService,
        private EventRepository $eventRepository,
        private CmsRepository $cmsRepository,
        private LoggerInterface $logger,
    ) {}

    public function getOrCreateKey(): string
    {
        $key = $this->appStateService->get(self::STATE_KEY);

        if ($key !== null && $key !== '') {
            return $key;
        }

        $key = bin2hex(random_bytes(16));
        $this->appStateService->set(self::STATE_KEY, $key);

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

        $status = $response->getStatusCode();

        if ($status !== 200 && $status !== 202) {
            $context = [
                'status' => $status,
                'host' => $host,
                'key_location' => $payload['keyLocation'],
                'url_count' => count($payload['urlList']),
                'response_body' => $response->getContent(false),
            ];
            $this->logger->error('Submit to IndexNow failed', $context);
            withScope(function (Scope $scope) use ($context): void {
                $scope->setContext('indexnow', $context);
                captureMessage('Submit to IndexNow failed', Severity::error());
            });
        }

        return [
            'status' => $status,
            'host' => $host,
        ];
    }

    public function getLastSubmittedAt(): ?DateTimeImmutable
    {
        $value = $this->appStateService->get(self::STATE_KEY_LAST_SUBMIT);

        if ($value === null || $value === '') {
            return null;
        }

        $dt = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $value);

        return $dt !== false ? $dt : null;
    }

    public function recordSubmission(): void
    {
        $this->appStateService->set(
            self::STATE_KEY_LAST_SUBMIT,
            (new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM),
        );
    }
}
