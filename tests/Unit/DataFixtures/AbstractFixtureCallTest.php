<?php declare(strict_types=1);

namespace Tests\Unit\DataFixtures;

use Error;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AbstractFixtureCallTest extends TestCase
{
    public function testInvalidMethodPrefixThrowsError(): void
    {
        $fixture = new DummyAbstractFixture();

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Call to undefined method: Tests\Unit\\DataFixtures\\DummyAbstractFixture::fooBar');

        /** @phpstan-ignore-next-line Intentionally calling unknown method to trigger __call */
        $fixture->fooBar('anything');
    }

    public function testMissingFixtureClassThrowsRuntimeException(): void
    {
        $fixture = new DummyAbstractFixture();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Class App\\DataFixtures\\NonExistingFixture does not exist!');

        /** @phpstan-ignore-next-line Magic call to trigger class_exists check */
        $fixture->getRefNonExisting('id');
    }

    public function testGetRefDelegatesWithProperKeyAndClassAndReturnsValue(): void
    {
        $fixture = new DummyAbstractFixture();

        $input = 'abc';
        /** @var object $result */
        /** @phpstan-ignore-next-line Magic call to __call */
        $result = $fixture->getRefLanguage($input);

        $expectedKey = 'LanguageFixture::' . md5((string)$input);
        $expectedClass = 'App\\DataFixtures\\LanguageFixture';

        $this->assertSame($expectedKey, $fixture->lastGetReferenceName, 'Delegated key does not match');
        $this->assertSame($expectedClass, $fixture->lastGetReferenceClass, 'Delegated class does not match');
        $this->assertSame($fixture->stubReturnedFromGetReference, $result, 'Return value should be passthrough from getReference');
    }

    public function testDebugging(): void
    {
        $fixture = new DummyAbstractFixture();
        $fixture->start('dfg');

    }
}
