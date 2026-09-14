<?php declare(strict_types=1);

namespace Tests\Unit\Circulation\Trust;

use App\Circulation\Trust\AccessProvider;
use App\Circulation\Trust\ContextIndex;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

class AccessProviderTest extends TestCase
{
    private const string CONTEXT = 'book-group-1';

    public function testItDeniesAdministrationRatherThanAbstaining(): void
    {
        // Arrange
        $provider = $this->provider(claimed: true);

        // Act
        $answer = $provider->canAdminister(self::CONTEXT, 5);

        // Assert
        self::assertFalse($answer);
    }

    public function testItAbstainsOnAContextCirculationDoesNotOwn(): void
    {
        // Arrange
        $provider = $this->provider(claimed: false);

        // Act
        $answer = $provider->canAdminister(self::CONTEXT, 5);

        // Assert
        self::assertNull($answer);
    }

    private function provider(bool $claimed): AccessProvider
    {
        $index = $this->createStub(ContextIndex::class);
        $index->method('itemTypeFor')->willReturn($claimed ? 'book' : null);

        return new AccessProvider($index, $this->createStub(UserRepository::class));
    }
}
