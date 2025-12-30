<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\CronCommand;
use App\Service\EmailService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class CronCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $emailServiceStub = $this->createStub(EmailService::class);
        $command = new CronCommand($emailServiceStub);

        $this->assertSame('app:cron', $command->getName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        $emailServiceStub = $this->createStub(EmailService::class);
        $command = new CronCommand($emailServiceStub);

        $this->assertSame('cron manager to be called often, maybe every 5 min or so', $command->getDescription());
    }
}
