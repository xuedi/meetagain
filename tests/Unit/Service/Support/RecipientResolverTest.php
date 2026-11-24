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
        $resolver = $this->makeResolver([
            $this->provider(null),
            $this->provider([$claimed]),
            $this->provider([$this->createStub(User::class)]),
        ], admins: [$this->createStub(User::class)]);

        // Act
        $recipients = $resolver->resolve($this->request(1));

        // Assert
        static::assertSame([$claimed], $recipients);
    }

    public function testAnEmptyClaimIsTreatedAsNoClaim(): void
    {
        // Arrange
        $admin = $this->createStub(User::class);
        $resolver = $this->makeResolver([$this->provider([])], admins: [$admin]);

        // Act
        $recipients = $resolver->resolve($this->request(1));

        // Assert
        static::assertSame([$admin], $recipients, 'A provider returning nothing must not swallow the request');
    }

    public function testWithoutAnyProviderTheAdminsGetIt(): void
    {
        // Arrange
        $admin = $this->createStub(User::class);
        $resolver = $this->makeResolver([], admins: [$admin]);

        // Act
        $recipients = $resolver->resolve($this->request(1));

        // Assert
        static::assertSame([$admin], $recipients);
    }

    public function testABatchHandsUnclaimedRequestsToTheAdminsWithOneLookup(): void
    {
        // Arrange
        $steward = $this->createStub(User::class);
        $admin = $this->createStub(User::class);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())->method('findAdminUsers')->willReturn([$admin]);
        $resolver = new RecipientResolver(new ArrayIterator([$this->provider(null, claims: [2 => [$steward]])]), $userRepository);

        // Act
        $recipients = $resolver->resolveMany([$this->request(1), $this->request(2), $this->request(3)]);

        // Assert
        static::assertSame([2 => [$steward], 1 => [$admin], 3 => [$admin]], $recipients);
    }

    public function testAnUnsavedRequestGoesToTheAdmins(): void
    {
        // Arrange
        $admin = $this->createStub(User::class);
        $resolver = $this->makeResolver([$this->provider([$this->createStub(User::class)])], admins: [$admin]);

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

    /** @param array<int, array<User>> $claims */
    private function provider(?array $recipients, array $claims = []): RecipientProviderInterface
    {
        $provider = $this->createStub(RecipientProviderInterface::class);
        $provider->method('getRecipients')->willReturn($recipients === null ? $claims : [1 => $recipients] + $claims);

        return $provider;
    }

    private function request(int $id): SupportRequest
    {
        $request = $this->createStub(SupportRequest::class);
        $request->method('getId')->willReturn($id);

        return $request;
    }
}
