<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity\Stubs;

use App\Entity\Event;

class EventStub extends Event
{
    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
