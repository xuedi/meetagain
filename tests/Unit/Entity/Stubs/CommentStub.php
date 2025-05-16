<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity\Stubs;

use App\Entity\Comment;

class CommentStub extends Comment
{
    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
