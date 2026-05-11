<?php

declare(strict_types=1);

namespace App\Command\Security;

use App\Entity\Incident;
use App\Enum\IncidentSeverity;
use App\Service\Security\BlockedSessionStore;
use App\Service\Security\SecurityService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Random\Randomizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:security:seed-blocks',
    description: 'Seed BlockedSessionStore with random session and IP blocks so the admin blocked-sessions list has visible entries.',
)]
final class SecuritySeedBlocksCommand extends Command
{
    private const array PROVIDERS = ['not_found', 'access_denied', 'rate_limit', 'fuse'];

    public function __construct(
        private readonly BlockedSessionStore $blockStore,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('count', InputArgument::OPTIONAL, 'How many session+IP pairs to seed', '5');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = max(1, (int) $input->getArgument('count'));
        $random = new Randomizer();

        $now = new DateTimeImmutable();
        for ($i = 0; $i < $count; ++$i) {
            $provider = self::PROVIDERS[$random->getInt(0, count(self::PROVIDERS) - 1)];
            $threat = $random->getInt(40, 99);
            $ip = sprintf(
                '%d.%d.%d.%d',
                $random->getInt(1, 223),
                $random->getInt(0, 255),
                $random->getInt(0, 255),
                $random->getInt(1, 254),
            );
            $sessionId = bin2hex($random->getBytes(13));

            $incident = new Incident();
            $incident->setIp($ip);
            $incident->setSessionId($sessionId);
            $incident->setTriggeredBy($provider);
            $incident->setSeverity($this->severityFor($threat));
            $incident->setProviderReports([[
                'providerKey' => $provider,
                'threatLevel' => $threat,
                'summary' => 'Seeded block (dev fixture)',
                'recommendation' => $provider === 'fuse' ? 'BlockShortCircuit' : 'Block',
                'details' => [],
            ]]);
            $incident->setBlockedUntilDescription(SecurityService::BLOCK_DURATION_LABEL);
            $incident->setUserAgent('Mozilla/5.0 (SeedFixture)');
            $incident->setStartedAt($now);
            $incident->setEndedAt($now);
            $incident->setCreatedAt($now);
            $incident->setUpdatedAt($now);
            $this->em->persist($incident);
            $this->em->flush();

            $snapshot = [
                'primaryProvider' => $provider,
                'maxThreatLevel' => $threat,
                'incidentId' => $incident->getId(),
            ];

            $this->blockStore->blockSession($sessionId, $snapshot);
            $this->blockStore->blockIp($ip, $snapshot);

            $output->writeln(sprintf(
                'Seeded incident #%d: %s / %s (provider=%s, threat=%d)',
                (int) $incident->getId(),
                $ip,
                substr($sessionId, 0, 16) . '...',
                $provider,
                $threat,
            ));
        }

        $output->writeln(sprintf('<info>Seeded %d session+IP block pair(s).</info>', $count));

        return Command::SUCCESS;
    }

    private function severityFor(int $threatLevel): IncidentSeverity
    {
        return match (true) {
            $threatLevel >= 90 => IncidentSeverity::Critical,
            $threatLevel >= 70 => IncidentSeverity::High,
            $threatLevel >= 40 => IncidentSeverity::Medium,
            default => IncidentSeverity::Low,
        };
    }
}
