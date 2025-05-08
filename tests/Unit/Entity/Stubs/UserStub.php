<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity\Stubs;

use App\Entity\User;

class UserStub extends User
{
    private ?int $id = null;

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
