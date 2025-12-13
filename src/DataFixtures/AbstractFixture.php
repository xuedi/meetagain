<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Cms;
use App\Entity\Event;
use App\Entity\Host;
use App\Entity\Location;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Error;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

/**
 * A bit of black magic to make fixtures more readable.
 * Also, method definitions for magic calls for phpStan
 *
 * @method User getRefUser(string $name)
 * @method void addRefUser(string $name, User $entity)
 *
 * @method Host getRefHost(string $name)
 * @method void addRefHost(string $name, Host $entity)
 *
 * @method Location getRefLocation(string $name)
 * @method void addRefLocation(string $name, Location $entity)
 *
 * @method Cms getRefCms(string $name)
 * @method void addRefCms(string $name, Cms $entity)
 *
 * @method Event getRefEvent(string $name)
 * @method void addRefEvent(string $name, Event $entity)
 */

abstract class AbstractFixture extends Fixture
{
    protected ?Filesystem $fs = null;

    public function __call($methodName, $params = null)
    {
        if (!in_array(substr((string) $methodName, 0, 6), ['getRef', 'addRef'])) {
            throw new Error('Call to undefined method: ' . static::class . '::' . $methodName);
        }

        $entityName = substr((string) $methodName, 6);
        $entityClass = sprintf("App\\Entity\\%s", $entityName);
        if (!class_exists($entityClass)) {
            throw new RuntimeException('Class ' . $entityClass . ' does not exist!');
        }

        $key = sprintf('%s::%s', $entityName . 'Fixture', md5((string)($params[0] ?? '')));
        switch (substr((string) $methodName, 0, 6)) {
            case 'getRef':
                try {
                    return $this->getReference($key, $entityClass);
                } catch (Throwable $exception) {
                    throw new RuntimeException(sprintf(
                        "Error retrieving reference '%s::%s' [%s]",
                        $entityName,
                        $params[0],
                        $exception->getMessage()
                    ), $exception->getCode(), $exception);
                }
            case 'addRef':
                $this->addReference($key, $params[1]);
                return null;
        }

        throw new RuntimeException('Hoping php code analyzer get better soon and see you can get here');
    }

    protected function getText(string $fileName): string
    {
        if (!$this->fs instanceof \Symfony\Component\Filesystem\Filesystem) {
            $this->fs = new Filesystem();
        }
        $file = sprintf('%s/%s/%s.txt', __DIR__, $this->getClassName(), $fileName);

        return $this->fs->readFile($file);
    }

    protected function start($dynamic = false): void
    {
        echo 'Creating ' . $this->getClassName() . ' ...';
    }

    protected function tick(): void
    {
        echo '.';
    }

    protected function stop(): void
    {
        echo ' OK' . PHP_EOL;
    }
    
    private function getClassName(): string
    {
        $chunks = explode('\\', static::class);

        return str_replace('Fixture', '', end($chunks));
    }
}
