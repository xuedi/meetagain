<?php declare(strict_types=1);

namespace App\Command\Security;

use App\Repository\AccessDeniedLogRepository;
use App\Repository\IncidentRepository;
use App\Repository\NotFoundLogRepository;
use App\Repository\RateLimitLogRepository;
use App\Service\Security\BlockedSessionStore;
use DateInterval;
use DateTimeImmutable;
use JsonException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:security:assert-state',
    description: 'Verify expected SecurityService post-attack state from a JSON expectations file. Emits PASS/FAIL per expectation; exits 0 if all pass.',
)]
final class SecurityAssertStateCommand extends Command
{
    public function __construct(
        private readonly BlockedSessionStore $blockStore,
        private readonly IncidentRepository $incidentRepo,
        private readonly NotFoundLogRepository $notFoundLogRepo,
        private readonly AccessDeniedLogRepository $accessDeniedLogRepo,
        private readonly RateLimitLogRepository $rateLimitLogRepo,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('expectations', InputArgument::REQUIRED, 'Path to expectations JSON file')
            ->addOption('scenario', null, InputOption::VALUE_REQUIRED, 'Scenario name for the verdict file', 'scenario')
            ->addOption(
                'output-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Where to write the verdict JSON',
                'tests/reports/attack',
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('expectations');
        if (!is_readable($path)) {
            $output->writeln(sprintf('<error>Expectations file not readable: %s</error>', $path));
            return Command::FAILURE;
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $output->writeln('<error>Failed to parse expectations: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $expectations = is_array($payload['expectations'] ?? null) ? $payload['expectations'] : [];
        $scenario = is_string($payload['scenario'] ?? null)
            ? $payload['scenario']
            : (string) $input->getOption('scenario');

        $verdicts = [];
        $allPassed = true;
        foreach ($expectations as $expectation) {
            $verdict = $this->evaluate(is_array($expectation) ? $expectation : []);
            $verdicts[] = $verdict;
            $output->writeln(sprintf(
                '%s	%s	%s',
                $verdict['passed'] ? 'PASS' : 'FAIL',
                $verdict['kind'],
                $verdict['detail'],
            ));
            if (!$verdict['passed']) {
                $allPassed = false;
            }
        }

        $outputDir = (string) $input->getOption('output-dir');
        if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true) && !is_dir($outputDir)) {
            $output->writeln(sprintf('<error>Could not create output dir: %s</error>', $outputDir));
            return Command::FAILURE;
        }

        $verdictPath = rtrim($outputDir, '/') . '/' . $scenario . '.verdict.json';
        $payloadOut = [
            'scenario' => $scenario,
            'allPassed' => $allPassed,
            'verdicts' => $verdicts,
            'evaluatedAt' => new DateTimeImmutable()->format(DATE_ATOM),
        ];
        try {
            file_put_contents($verdictPath, json_encode($payloadOut, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } catch (JsonException $e) {
            $output->writeln('<error>Failed to write verdict: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln(
            $allPassed ? '<info>All expectations passed.</info>' : '<error>One or more expectations failed.</error>',
        );
        $output->writeln(sprintf('Verdict written to %s', $verdictPath));

        return $allPassed ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param array<string, mixed> $expectation
     * @return array{kind: string, passed: bool, detail: string, expected: mixed, actual: mixed}
     */
    private function evaluate(array $expectation): array
    {
        $kind = is_string($expectation['kind'] ?? null) ? $expectation['kind'] : 'unknown';

        return match ($kind) {
            'ipBlocked' => $this->evalIpBlocked($expectation),
            'sessionBlocked' => $this->evalSessionBlocked($expectation),
            'incidentCount' => $this->evalIncidentCount($expectation),
            'logRowCount' => $this->evalLogRowCount($expectation),
            'blockSnapshotPrimaryProvider' => $this->evalBlockSnapshotPrimaryProvider($expectation),
            default => [
                'kind' => $kind,
                'passed' => false,
                'detail' => 'unknown expectation kind',
                'expected' => null,
                'actual' => null,
            ],
        };
    }

    /**
     * @param array<string, mixed> $expectation
     * @return array{kind: string, passed: bool, detail: string, expected: mixed, actual: mixed}
     */
    private function evalIpBlocked(array $expectation): array
    {
        $ip = is_string($expectation['ip'] ?? null) ? $expectation['ip'] : '';
        $expected = (bool) ($expectation['expected'] ?? true);
        $actual = $this->blockStore->isIpBlocked($ip);

        return [
            'kind' => 'ipBlocked',
            'passed' => $actual === $expected,
            'detail' => sprintf('ip=%s expected=%s actual=%s', $ip, $this->bool($expected), $this->bool($actual)),
            'expected' => $expected,
            'actual' => $actual,
        ];
    }

    /**
     * @param array<string, mixed> $expectation
     * @return array{kind: string, passed: bool, detail: string, expected: mixed, actual: mixed}
     */
    private function evalSessionBlocked(array $expectation): array
    {
        $sessionId = is_string($expectation['sessionId'] ?? null) ? $expectation['sessionId'] : '';
        $expected = (bool) ($expectation['expected'] ?? true);
        $actual = $this->blockStore->isSessionBlocked($sessionId);

        return [
            'kind' => 'sessionBlocked',
            'passed' => $actual === $expected,
            'detail' => sprintf(
                'sessionId=%s expected=%s actual=%s',
                $sessionId,
                $this->bool($expected),
                $this->bool($actual),
            ),
            'expected' => $expected,
            'actual' => $actual,
        ];
    }

    /**
     * @param array<string, mixed> $expectation
     * @return array{kind: string, passed: bool, detail: string, expected: mixed, actual: mixed}
     */
    private function evalIncidentCount(array $expectation): array
    {
        $since = $this->resolveSince($expectation['since'] ?? 'PT5M');
        $triggeredBy = is_string($expectation['triggeredBy'] ?? null) ? $expectation['triggeredBy'] : null;
        $actual = $triggeredBy !== null
            ? $this->incidentRepo->countSinceByTriggeredBy($since, $triggeredBy)
            : $this->incidentRepo->countSince($since);

        $passed = $this->compareCount($actual, $expectation);
        $expectedDescription = $this->describeCountExpectation($expectation);

        return [
            'kind' => 'incidentCount',
            'passed' => $passed,
            'detail' => sprintf('triggeredBy=%s %s actual=%d', $triggeredBy ?? 'any', $expectedDescription, $actual),
            'expected' => $expectedDescription,
            'actual' => $actual,
        ];
    }

    /**
     * @param array<string, mixed> $expectation
     * @return array{kind: string, passed: bool, detail: string, expected: mixed, actual: mixed}
     */
    private function evalLogRowCount(array $expectation): array
    {
        $since = $this->resolveSince($expectation['since'] ?? 'PT5M');
        $table = is_string($expectation['table'] ?? null) ? $expectation['table'] : '';

        $actual = match ($table) {
            'logs_not_found' => $this->notFoundLogRepo->countSince($since),
            'logs_access_denied' => $this->accessDeniedLogRepo->countSince($since),
            'logs_rate_limit' => $this->rateLimitLogRepo->countSince($since),
            default => -1,
        };

        if ($actual < 0) {
            return [
                'kind' => 'logRowCount',
                'passed' => false,
                'detail' => sprintf('unknown table=%s', $table),
                'expected' => null,
                'actual' => null,
            ];
        }

        $passed = $this->compareCount($actual, $expectation);
        $expectedDescription = $this->describeCountExpectation($expectation);

        return [
            'kind' => 'logRowCount',
            'passed' => $passed,
            'detail' => sprintf('table=%s %s actual=%d', $table, $expectedDescription, $actual),
            'expected' => $expectedDescription,
            'actual' => $actual,
        ];
    }

    /**
     * @param array<string, mixed> $expectation
     * @return array{kind: string, passed: bool, detail: string, expected: mixed, actual: mixed}
     */
    private function evalBlockSnapshotPrimaryProvider(array $expectation): array
    {
        $ip = is_string($expectation['ip'] ?? null) ? $expectation['ip'] : '';
        $expected = is_string($expectation['expected'] ?? null) ? $expectation['expected'] : '';
        $snapshot = $this->blockStore->getIpSnapshot($ip);
        $actual = is_array($snapshot) && is_string($snapshot['primaryProvider'] ?? null)
            ? $snapshot['primaryProvider']
            : null;

        return [
            'kind' => 'blockSnapshotPrimaryProvider',
            'passed' => $actual === $expected,
            'detail' => sprintf('ip=%s expected=%s actual=%s', $ip, $expected, $actual ?? 'null'),
            'expected' => $expected,
            'actual' => $actual,
        ];
    }

    private function resolveSince(mixed $raw): DateTimeImmutable
    {
        $duration = is_string($raw) ? $raw : 'PT5M';
        try {
            return new DateTimeImmutable()->sub(new DateInterval($duration));
        } catch (\Exception) {
            return new DateTimeImmutable()->sub(new DateInterval('PT5M'));
        }
    }

    /**
     * @param array<string, mixed> $expectation
     */
    private function compareCount(int $actual, array $expectation): bool
    {
        if (array_key_exists('expected', $expectation) && is_int($expectation['expected'])) {
            return $actual === $expectation['expected'];
        }
        if (array_key_exists('lessThanOrEqual', $expectation) && is_int($expectation['lessThanOrEqual'])) {
            return $actual <= $expectation['lessThanOrEqual'];
        }
        if (array_key_exists('greaterThanOrEqual', $expectation) && is_int($expectation['greaterThanOrEqual'])) {
            return $actual >= $expectation['greaterThanOrEqual'];
        }
        return false;
    }

    /**
     * @param array<string, mixed> $expectation
     */
    private function describeCountExpectation(array $expectation): string
    {
        if (array_key_exists('expected', $expectation) && is_int($expectation['expected'])) {
            return sprintf('expected=%d', $expectation['expected']);
        }
        if (array_key_exists('lessThanOrEqual', $expectation) && is_int($expectation['lessThanOrEqual'])) {
            return sprintf('lessThanOrEqual=%d', $expectation['lessThanOrEqual']);
        }
        if (array_key_exists('greaterThanOrEqual', $expectation) && is_int($expectation['greaterThanOrEqual'])) {
            return sprintf('greaterThanOrEqual=%d', $expectation['greaterThanOrEqual']);
        }
        return 'expected=?';
    }

    private function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
