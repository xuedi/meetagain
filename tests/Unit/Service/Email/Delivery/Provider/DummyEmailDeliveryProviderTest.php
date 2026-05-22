<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email\Delivery\Provider;

use App\Service\Email\Delivery\EmailDeliveryLogFilter;
use App\Service\Email\Delivery\Provider\DummyEmailDeliveryProvider;
use PHPUnit\Framework\TestCase;

class DummyEmailDeliveryProviderTest extends TestCase
{
    public function testIsAvailableIsAlwaysFalse(): void
    {
        static::assertFalse(new DummyEmailDeliveryProvider()->isAvailable());
    }

    public function testGetLogsReturnsEmptyCollection(): void
    {
        // Act
        $collection = new DummyEmailDeliveryProvider()->getLogs(new EmailDeliveryLogFilter());

        // Assert
        static::assertTrue($collection->isEmpty());
        static::assertSame(0, $collection->total);
    }

    public function testGetLogByMessageIdReturnsNull(): void
    {
        static::assertNull(new DummyEmailDeliveryProvider()->getLogByMessageId('any-id'));
    }
}
