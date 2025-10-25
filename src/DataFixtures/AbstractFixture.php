<?php declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Error;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractFixture extends Fixture
{
    protected ?Filesystem $fs = null;

    public function __call($methodName, $params = null)
    {
        if (!in_array(substr($methodName, 0, 6), ['getRef', 'addRef'])) {
            throw new Error('Call to undefined method: ' . static::class . '::' . $methodName);
        }

        $entityName = substr($methodName, 6);
        $fixtureClassName = sprintf("App\\DataFixtures\\%sFixture", $entityName);
        if (!class_exists($fixtureClassName)) {
            throw new RuntimeException('Class ' . $fixtureClassName . ' does not exist!');
        }

        $key = sprintf('%s::%s', $entityName . 'Fixture', md5((string)($params[0] ?? '')));
        switch (substr($methodName, 0, 6)) {
            case 'getRef':
                return $this->getReference($key, $fixtureClassName);
            case 'addRef':
                $this->addReference($key, $params[1]);
                return null;
        }

        throw new RuntimeException('Hoping php code analyzer get better soon and see you can get here');
    }

    protected function getText(string $fileName): string
    {
        if ($this->fs === null) {
            $this->fs = new Filesystem();
        }
        $file = sprintf('%s/%s/%s.txt', __DIR__, $this->getClassName(), $fileName);

        return $this->fs->readFile($file);
    }

    public function start($dynamic = false): void
    {
        echo 'Creating ' . $this->getClassName() . ' ...';
    }

    public function tick(): void
    {
        echo '.';
    }

    public function stop(): void
    {
        echo ' OK' . PHP_EOL;
    }
    
    private function getClassName(): string
    {
        $chunks = explode('\\', get_class($this));

        return str_replace('Fixture', '', end($chunks));
    }
}
