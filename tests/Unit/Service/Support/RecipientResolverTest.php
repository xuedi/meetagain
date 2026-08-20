<?php declare(strict_types=1);

namespace Tests\Unit\Service\Support;

use App\Entity\SupportRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Support\RecipientProviderInterface;
use App\Service\Support\RecipientResolver;
use ArrayIterator;
use PHPUnit\Framework\TestCase;

class RecipientResolverTest extends TestCase
{
    public function testTheFirstProviderThatClaimsTheRequestWins(): void
    {
        // Arrange
        $claimed = $this->createStub(User::class);
        $resolver = $this->makeResolver(
            [$this->provider(null), $this->provider([$claimed]), $this->provider([$this->createStub(User::class)])],
            admins: [$this->createStub(User::class)],
        );

        // Act
        $recipients = $resolver->resolve($this->createStub(SupportRequest::class));

        // Assert
        static::assertSame([$claimed], $recipients);
    }

    public function testAnEmptyClaimIsTreatedAsNoClaim(): void
    {
        // Arrange
        $admin = $this->createStub(User::class);
        $resolver = $this->makeResolver([$this->provider([])], admins: [$admin]);

        // Act
        $recipients = $resolver->resolve($this->createStub(SupportRequest::class));

        // Assert
        static::assertSame([$admin], $recipients, 'A provider returning nothing must not swallow the request');
    }

    public function testWithoutAnyProviderTheAdminsGetIt(): void
    {
        // Arrange
        $admin = $this->createStub(User::class);
        $resolver = $this->makeResolver([], admins: [$admin]);

        // Act
        $recipients = $resolver->resolve($this->createStub(SupportRequest::class));

        // Assert
        static::assertSame([$admin], $recipients);
    }

    /**
     * @param array<RecipientProviderInterface> $providers
     * @param array<User> $admins
     */
    private function makeResolver(array $providers, array $admins): RecipientResolver
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findAdminUsers')->willReturn($admins);

        return new RecipientResolver(new ArrayIterator($providers), $userRepository);
    }

    /** @param array<User>|null $recipients */
    private function provider(?array $recipients): RecipientProviderInterface
    {
        $provider = $this->createStub(RecipientProviderInterface::class);
        $provider->method('getRecipients')->willReturn($recipients);

        return $provider;
    }
}
