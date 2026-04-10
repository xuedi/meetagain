<?php declare(strict_types=1);

namespace App\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:indexnow:submit', description: 'Submit all hosted domains to IndexNow (Bing)')]
class IndexNowSubmitCommand extends Command
{
    private const KEY = '6fdbe8408abb4a2687e50d51ecee70e8';
    private const KEY_FILE = '6fdbe8408abb4a2687e50d51ecee70e8.txt';

    /**
     * Each entry is a host with the URLs to submit for that host.
     * IndexNow requires all urlList entries to match the host — one request per domain.
     * The key file is served by the same Symfony app on all custom domains.
     */
    private const DOMAINS = [
        'meetagain.org' => [
            'https://meetagain.org',
        ],
        'dragon-descendants.de' => [
            'https://dragon-descendants.de',
        ],
        'travolta-meetup.de' => [
            'https://travolta-meetup.de',
        ],
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $failed = false;

        foreach (self::DOMAINS as $host => $urlList) {
            $payload = [
                'host' => $host,
                'key' => self::KEY,
                'keyLocation' => "https://{$host}/" . self::KEY_FILE,
                'urlList' => $urlList,
            ];

            $response = $this->httpClient->request('POST', 'https://api.indexnow.org/IndexNow', [
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();

            match (true) {
                $statusCode === 200 => $output->writeln("<info>[{$host}] Accepted (200)</info>"),
                $statusCode === 202 => $output->writeln("<comment>[{$host}] Pending key validation (202)</comment>"),
                default => (function () use ($output, $host, $statusCode, $response, &$failed): void {
                    $output->writeln("<error>[{$host}] Failed: HTTP {$statusCode}</error>");
                    $output->writeln($response->getContent(false));
                    $failed = true;
                })(),
            };
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
