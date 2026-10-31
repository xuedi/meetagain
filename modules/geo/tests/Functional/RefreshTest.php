<?php declare(strict_types=1);

namespace Module\Geo\Tests\Functional;

use Module\Geo\Internal\Refresh;
use Module\Task\Contract\Status;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RefreshTest extends KernelTestCase
{
    public function testTheRefreshDoesNothingWhileNoCountryIsImported(): void
    {
        // Arrange
        self::bootKernel();
        $refresh = self::getContainer()->get(Refresh::class);

        // Act
        $result = $refresh->run();

        // Assert
        self::assertSame([Status::ok, 'No country imported, nothing to refresh'], [$result->status, $result->message]);
    }
}
