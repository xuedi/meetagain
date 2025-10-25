<?php declare(strict_types=1);

namespace Tests\Unit\DataFixtures;

use App\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

class DummyAbstractFixture extends AbstractFixture
{
    /** @var string|null */
    public $lastGetReferenceName;
    /** @var string|null */
    public $lastGetReferenceClass;
    /** @var object */
    public $stubReturnedFromGetReference;

    public function __construct()
    {
        $stub = new \stdClass();
        $stub->tag = 'from-getReference';
        $this->stubReturnedFromGetReference = $stub;
    }

    // Satisfy Doctrine FixtureInterface
    public function load(ObjectManager $manager): void
    {
        // no-op
    }

    // Override to avoid interacting with Doctrine ReferenceRepository in unit test
    public function getReference(string $name, string $class): object
    {
        $this->lastGetReferenceName = $name;
        $this->lastGetReferenceClass = $class;

        return $this->stubReturnedFromGetReference;
    }

    public function setReference(string $name, object $object): void
    {
        // no-op for these tests
    }
}