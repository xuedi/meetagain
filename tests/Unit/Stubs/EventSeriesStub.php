<?php declare(strict_types=1);

namespace Tests\Unit\Stubs;

use App\Entity\EventSeries;

class EventSeriesStub extends EventSeries
{
    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }
}
