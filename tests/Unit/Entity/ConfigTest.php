<?php declare(strict_types=1);

namespace Unit\Entity;

use App\Entity\Config;
use App\Entity\ConfigType;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testSetterGetter(): void
    {
        $expectedType = ConfigType::String;
        $expectedName = 'TestName';
        $expectedValue = 'Over9000';

        $subject = new Config();
        $subject->setType($expectedType);
        $subject->setName($expectedName);
        $subject->setValue($expectedValue);

        $this->assertSame($subject->getName(), $expectedName, 'Failed property match: Name');
        $this->assertSame($subject->getValue(), $expectedValue, 'Failed property match: Value');
        $this->assertSame($subject->getType(), $expectedType, 'Failed property match: Type');
        $this->assertNull($subject->getId());
    }
}
