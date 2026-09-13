<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\Event;
use App\Entity\Host;
use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class HostsSection implements SectionInterface
{
    private const int NAME_LENGTH = 16;

    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'hosts';
    }

    #[Override]
    public function getOrder(): int
    {
        return 25;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        if ($scope->eventIds === []) {
            return [];
        }

        $hosts = [];
        foreach ($this->em->getRepository(Event::class)->findBy(['id' => $scope->eventIds], ['id' => 'ASC']) as $event) {
            foreach ($event->getHost() as $host) {
                $hosts[(int) $host->getId()] = $host;
            }
        }
        ksort($hosts);

        $rows = [];
        foreach ($hosts as $hostId => $host) {
            $user = $host->getUser();
            $rows[] = [
                'ref' => $hostId,
                'name' => $host->getName(),
                'email' => $scope->grants($user, DataCategory::Profile) ? $user?->getEmail() : null,
            ];
        }

        return $rows;
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        $createdWithoutUser = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = mb_substr((string) ($row['name'] ?? ''), 0, self::NAME_LENGTH);
            $user = $context->resolveRef(User::class, $row['email'] ?? null);

            $host = $user instanceof User ? $this->findHostOf($user) : $createdWithoutUser[$name] ?? $this->findUnlinkedHost($name);
            if ($host instanceof Host) {
                $context->count($this->getKey(), Outcome::Matched);
            } else {
                $host = new Host();
                $host->setName($name);
                $host->setUser($user);
                $this->em->persist($host);
                $context->count($this->getKey(), Outcome::Created);

                if (!$user instanceof User) {
                    $createdWithoutUser[$name] = $host;
                }
            }

            $context->mapRef(Host::class, (int) ($row['ref'] ?? 0), $host);
        }
    }

    private function findHostOf(User $user): ?Host
    {
        return $this->em->getRepository(Host::class)->findOneBy(['user' => $user]);
    }

    private function findUnlinkedHost(string $name): ?Host
    {
        return $this->em->getRepository(Host::class)->findOneBy(['name' => $name, 'user' => null]);
    }
}
