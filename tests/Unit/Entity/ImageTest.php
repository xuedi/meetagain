<?php declare(strict_types=1);

namespace Unit\Entity;

use App\Entity\Config;
use App\Entity\ConfigType;
use App\Entity\Host;
use App\Entity\Image;
use App\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase
{
    public function testSetterGetter(): void
    {
        $expectedCreatedAt = new DateTimeImmutable();
        $expectedAltText = 'TestAlt';
        $expectedUser = new User();
        $expectedHash = md5('test');
        $expectedExtension = 'jpg';
        $expectedSize = 123456;
        $expectedMimeType = 'image/jpeg';

        $subject = new Image();
        $subject->setCreatedAt($expectedCreatedAt);
        $subject->setAlt($expectedAltText);
        $subject->setUploader($expectedUser);
        $subject->setHash($expectedHash);
        $subject->setExtension($expectedExtension);
        $subject->setSize($expectedSize);
        $subject->setMimeType($expectedMimeType);


        $this->assertSame($subject->getCreatedAt(), $expectedCreatedAt);
        $this->assertSame($subject->getAlt(), $expectedAltText);
        $this->assertSame($subject->getUploader(), $expectedUser);
        $this->assertSame($subject->getHash(), $expectedHash);
        $this->assertSame($subject->getExtension(), $expectedExtension);
        $this->assertSame($subject->getSize(), $expectedSize);
        $this->assertSame($subject->getMimeType(), $expectedMimeType);
        $this->assertNull($subject->getId());
    }
}
