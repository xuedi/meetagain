<?php declare(strict_types=1);

namespace Tests\Unit\Stubs;

use App\Entity\User;

class UserStub extends User
{
    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }
}
