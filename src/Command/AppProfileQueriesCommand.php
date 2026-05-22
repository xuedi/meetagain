<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Service\Test\RouteDiscoverer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\KernelInterface;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector as BundleDoctrineDataCollector;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;

#[AsCommand(
    name: 'app:profile:queries',
    description: 'Profile every discoverable GET route and report query counts to identify caching candidates.',
)]
class AppProfileQueriesCommand extends Command
{
    private const string ADMIN_EMAIL = 'admin@example.org';
    private const string ADMIN_URL_PREFIX = '/admin/';

    public function __construct(
        private readonly RouteDiscoverer $routeDiscoverer,
        private readonly KernelInterface $kernel,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $kernelProjectDir,
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('anonymous-only', null, InputOption::VALUE_NONE, 'Skip the authenticated admin pass')
            ->addOption('admin-only', null, InputOption::VALUE_NONE, 'Skip the anonymous pass')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Cap the number of routes processed per pass')
            ->addOption('json', null, InputOption::VALUE_REQUIRED, 'Override JSON output path')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'In the console table, only show routes with at least N queries', 0)
            ->addOption('dump-sql', null, InputOption::VALUE_REQUIRED, 'Dump grouped SQL for a single URL instead of running the sweep (investigation mode)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->environment !== 'test') {
            $output->writeln('STATUS: ERROR');
            $output->writeln('---');
            $output->writeln('This command requires the test environment (profiler + KernelBrowser login).');
            $output->writeln('Run via `just appProfileQueries` (which sets --env=test) instead of `just app`.');
            return Command::FAILURE;
        }

        $anonymousOnly = (bool) $input->getOption('anonymous-only');
        $adminOnly = (bool) $input->getOption('admin-only');
        $limit = $input->getOption('limit');
        $limit = $limit === null ? null : max(1, (int) $limit);
        $threshold = max(0, (int) $input->getOption('threshold'));
        $jsonPath = (string) ($input->getOption('json') ?? $this->kernelProjectDir . '/tests/reports/query-profile.json');

        $dumpUrl = $input->getOption('dump-sql');
        if ($dumpUrl !== null) {
            return $this->dumpSql($output, (string) $dumpUrl, $adminOnly);
        }

        if ($anonymousOnly && $adminOnly) {
            $output->writeln('STATUS: ERROR');
            $output->writeln('---');
            $output->writeln('--anonymous-only and --admin-only are mutually exclusive.');
            return Command::FAILURE;
        }

        $urls = $this->routeDiscoverer->discoverGetUrls();
        if ($urls === []) {
            $output->writeln('STATUS: ERROR');
            $output->writeln('---');
            $output->writeln('No routes discovered. Is the test database populated?');
            return Command::FAILURE;
        }

        $results = [];

        if (!$adminOnly) {
            $anonymousUrls = $limit === null ? $urls : array_slice($urls, 0, $limit);
            $results = array_merge($results, $this->sweep($anonymousUrls, authenticated: false));
        }

        if (!$anonymousOnly) {
            $adminUrls = array_values(array_filter($urls, static fn(string $u): bool => str_contains($u, self::ADMIN_URL_PREFIX)));
            if ($limit !== null) {
                $adminUrls = array_slice($adminUrls, 0, $limit);
            }
            $results = array_merge($results, $this->sweep($adminUrls, authenticated: true));
        }

        usort($results, static fn(array $a, array $b): int => $b['query_count'] <=> $a['query_count']);

        $this->writeJson($jsonPath, $results);
        $this->renderConsole($output, $results, $threshold, $jsonPath);

        return Command::SUCCESS;
    }

    private function dumpSql(OutputInterface $output, string $url, bool $authenticated): int
    {
        $client = new KernelBrowser($this->kernel);
        $client->disableReboot();
        $client->catchExceptions(true);

        if ($authenticated) {
            $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => self::ADMIN_EMAIL]);
            if ($admin === null) {
                $output->writeln('STATUS: ERROR');
                $output->writeln('---');
                $output->writeln('Admin user not found.');
                return Command::FAILURE;
            }
            $client->loginUser($admin);
        }

        $client->request('GET', $url);
        $status = $client->getResponse()->getStatusCode();
        $profile = $client->getProfile();

        if ($profile === false || !$profile->hasCollector('db')) {
            $output->writeln('STATUS: ERROR');
            $output->writeln('---');
            $output->writeln('Profile unavailable.');
            return Command::FAILURE;
        }

        $db = $profile->getCollector('db');
        if (!$db instanceof DoctrineDataCollector) {
            return Command::FAILURE;
        }

        $output->writeln(sprintf('URL: %s', $url));
        $output->writeln(sprintf('STATUS: %d', $status));
        $output->writeln(sprintf('QUERIES: %d', $db->getQueryCount()));
        $output->writeln('---');

        if ($db instanceof BundleDoctrineDataCollector) {
            foreach ($db->getGroupedQueries() as $connectionGroups) {
                foreach ($connectionGroups as $row) {
                    $sql = (string) preg_replace('/\s+/', ' ', (string) $row['sql']);
                    $count = (int) ($row['count'] ?? 1);
                    $marker = $count > 1 ? sprintf('[x%-3d]', $count) : '       ';
                    $output->writeln(sprintf('%s %s', $marker, mb_substr($sql, 0, 220)));
                }
            }
            return Command::SUCCESS;
        }

        foreach ($db->getQueries() as $connectionQueries) {
            foreach ($connectionQueries as $q) {
                $sql = (string) preg_replace('/\s+/', ' ', (string) $q['sql']);
                $output->writeln('        ' . mb_substr($sql, 0, 220));
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param string[] $urls
     * @return list<array<string, mixed>>
     */
    private function sweep(array $urls, bool $authenticated): array
    {
        if ($urls === []) {
            return [];
        }

        $client = new KernelBrowser($this->kernel);
        $client->disableReboot();
        $client->catchExceptions(true);

        if ($authenticated) {
            $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => self::ADMIN_EMAIL]);
            if ($admin === null) {
                return [];
            }
            $client->loginUser($admin);
        }

        $rows = [];
        foreach ($urls as $url) {
            $client->request('GET', $url);
            $status = $client->getResponse()->getStatusCode();
            $profile = $client->getProfile();

            $rows[] = $this->buildRow($url, $status, $profile, $authenticated);
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function buildRow(string $url, int $status, Profile|false $profile, bool $authenticated): array
    {
        $queryCount = 0;
        $durationMs = 0.0;
        $flag = null;

        if ($profile === false) {
            $flag = 'profile_unavailable';
        } else {
            if ($profile->hasCollector('db')) {
                $db = $profile->getCollector('db');
                if ($db instanceof DoctrineDataCollector) {
                    $queryCount = $db->getQueryCount();
                }
            }

            if ($profile->hasCollector('time')) {
                $time = $profile->getCollector('time');
                if ($time instanceof TimeDataCollector) {
                    $durationMs = round($time->getDuration(), 2);
                }
            }
        }

        if ($flag === null && $status >= 500) {
            $flag = '5xx';
        } elseif ($flag === null && $status >= 300 && $status < 400) {
            $flag = 'redirect';
        }

        return [
            'url' => $url,
            'method' => 'GET',
            'status' => $status,
            'query_count' => $queryCount,
            'duration_ms' => $durationMs,
            'authenticated' => $authenticated,
            'flag' => $flag,
        ];
    }

    /** @param list<array<string, mixed>> $results */
    private function writeJson(string $path, array $results): void
    {
        $payload = [
            'generated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'route_count' => count($results),
            'results' => $results,
        ];

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, recursive: true);
        }

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /** @param list<array<string, mixed>> $results */
    private function renderConsole(OutputInterface $output, array $results, int $threshold, string $jsonPath): void
    {
        $fiveXx = array_values(array_filter($results, static fn(array $r): bool => $r['flag'] === '5xx'));
        $redirects = array_values(array_filter($results, static fn(array $r): bool => $r['flag'] === 'redirect'));

        $output->writeln(sprintf(
            'QUERY-PROFILE: %d routes, %d with 5xx, %d redirects',
            count($results),
            count($fiveXx),
            count($redirects),
        ));
        $output->writeln('---');

        $this->renderTopSection($output, $results, $threshold, authenticated: false, header: 'TOP BY QUERY COUNT (anon):');
        $this->renderTopSection($output, $results, $threshold, authenticated: true, header: 'TOP BY QUERY COUNT (admin):');

        if ($fiveXx !== []) {
            $output->writeln('');
            $output->writeln('WARNINGS (5xx, excluded from rankings):');
            foreach ($fiveXx as $row) {
                $output->writeln(sprintf('   %d   %s', $row['status'], $row['url']));
            }
        }

        $output->writeln('');
        $output->writeln("JSON: {$jsonPath}");
    }

    /** @param list<array<string, mixed>> $results */
    private function renderTopSection(OutputInterface $output, array $results, int $threshold, bool $authenticated, string $header): void
    {
        $rows = array_values(array_filter(
            $results,
            static fn(array $r): bool => $r['authenticated'] === $authenticated
                && $r['flag'] === null
                && (int) $r['query_count'] >= $threshold,
        ));

        if ($rows === []) {
            return;
        }

        $output->writeln($header);
        foreach ($rows as $row) {
            $output->writeln(sprintf(
                '   %3d q / %6.1f ms   %s',
                (int) $row['query_count'],
                (float) $row['duration_ms'],
                $row['url'],
            ));
        }
    }
}
