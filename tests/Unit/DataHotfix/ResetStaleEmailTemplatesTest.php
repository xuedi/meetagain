<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix;

use App\DataHotfix\Hotfixes\ResetStaleEmailTemplates;
use Module\Email\Contract\TemplatesInterface;
use PHPUnit\Framework\TestCase;

class ResetStaleEmailTemplatesTest extends TestCase
{
    public function testResetsExactlyTheStaleTemplates(): void
    {
        // Arrange
        $reset = [];
        $templates = $this->createStub(TemplatesInterface::class);
        $templates
            ->method('resetToDefault')
            ->willReturnCallback(static function (string $identifier) use (&$reset): void {
                $reset[] = $identifier;
            });

        // Act
        new ResetStaleEmailTemplates($templates)->execute();

        // Assert
        static::assertSame(
            [
                'support_notification',
                'verification_request',
                'welcome',
                'password_reset_request',
                'notification_message',
                'notification_rsvp_aggregated',
                'notification_event_canceled',
                'announcement',
            ],
            $reset,
        );
    }
}
