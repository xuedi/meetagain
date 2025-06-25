<?php declare(strict_types=1);

namespace Test\Unit\Stubs;

use App\Entity\Event;

class EventStub extends Event
{
    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }
}