<?php declare(strict_types=1);

namespace Module\Trust\Tests\Stub;

use App\Repository\UserRepository;

final readonly class UserLocator
{
    public const string ROOT_EMAIL = 'Admin@example.org';
    public const string EARNER_EMAIL = 'Adem.Lane@example.org';
    public const string NEWCOMER_EMAIL = 'Abraham.Baker@example.org';

    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function idFor(string $email): ?int
    {
        return $this->userRepository->findOneBy(['email' => $email])?->getId();
    }
}
