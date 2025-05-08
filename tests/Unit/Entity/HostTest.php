<?php declare(strict_types=1);

namespace Unit\Entity;

use App\Entity\Host;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class HostTest extends TestCase
{
    public function testSetterGetter(): void
    {
        $expectedName = 'TestName';
        $expectedUser = new User();

        $subject = new Host();
        $subject->setName($expectedName);
        $subject->setUser($expectedUser);

        $this->assertSame($subject->getName(), $expectedName);
        $this->assertSame($subject->getUser(), $expectedUser);
        $this->assertNull($subject->getId());
    }
}
