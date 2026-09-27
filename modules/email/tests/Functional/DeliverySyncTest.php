<?php declare(strict_types=1);

namespace Module\Email\Tests\Functional;

use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\SendlogInterface;
use Module\Email\Internal\Delivery\StatusSyncService;
use Module\Email\Internal\EmailService;
use Module\Email\Tests\Stub\DeliveryProvider;
use Module\Email\Tests\Stub\TriggeredEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DeliverySyncTest extends KernelTestCase
{
    use SeedsTemplates;

    public function testASentRowTakesTheStatusTheProviderReports(): void
    {
        // Arrange
        self::bootKernel();
        self::seedTemplates(self::$kernel);
        $container = self::getContainer();
        $container->get(DeliveryProvider::class)->status = 'bounced';
        $container->get(MailerInterface::class)->send($container->get(TriggeredEmail::class), ['recipients' => ['member@module-test.example']]);
        $container->get(EmailService::class)->sendQueue();

        // Act
        $result = $container->get(StatusSyncService::class)->syncPending();

        // Assert
        self::assertSame([1, 1], [$result->updated, $result->checked]);
        self::assertSame('bounced', $container->get(SendlogInterface::class)->list(template: TriggeredEmail::IDENTIFIER)[0]->providerStatus);
    }

    public function testAPendingRowIsNotAskedAbout(): void
    {
        // Arrange
        self::bootKernel();
        self::seedTemplates(self::$kernel);
        $container = self::getContainer();
        $container->get(MailerInterface::class)->send($container->get(TriggeredEmail::class), ['recipients' => ['member@module-test.example']]);

        // Act
        $result = $container->get(StatusSyncService::class)->syncPending();

        // Assert
        self::assertSame(0, $result->checked);
    }
}
