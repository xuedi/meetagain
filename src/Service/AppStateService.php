<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\AppState;
use App\Repository\AppStateRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

readonly class AppStateService
{
    public function __construct(
        private AppStateRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {}

    public function get(string $key): ?string
    {
        return $this->repository->findByKey($key)?->getValue();
    }

    public function set(string $key, string $value): void
    {
        $entry = $this->repository->findByKey($key);

        if ($entry === null) {
            $entry = new AppState($key, $value, new DateTimeImmutable('now'));
            $this->entityManager->persist($entry);
        } else {
            $entry->setValue($value);
            $entry->setUpdatedAt(new DateTimeImmutable('now'));
        }

        $this->entityManager->flush();
    }

    public function remove(string $key): void
    {
        $entry = $this->repository->findByKey($key);

        if ($entry === null) {
            return;
        }

        $this->entityManager->remove($entry);
        $this->entityManager->flush();
    }
}
