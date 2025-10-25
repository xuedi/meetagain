<?php declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Error;
use RuntimeException;

abstract class AbstractFixture extends Fixture
{
    public function __call($methodName, $params = null): object
    {
        if (!in_array(substr($methodName, 0, 6), ['getRef', 'addRef'])) {
            throw new Error('Call to undefined method: ' . static::class . '::' . $methodName);
        }

        $name = substr($methodName, 6) . 'Fixture';
        $className = __NAMESPACE__ . '\\' . $name;
        if (!class_exists($className)) {
            throw new RuntimeException('Class ' . $className . ' does not exist!');
        }

        $key = sprintf('%s::%s', $name, md5((string)$params[0]));
        switch (substr($methodName, 0, 6)) {
            case 'getRef':
                return $this->getReference($key, $className);
            case 'addRef':
                return $this->setReference($key, $params[0]);
        }

        throw new RuntimeException('Hoping php code analyzer get better soon and see you can get here');
    }
}
