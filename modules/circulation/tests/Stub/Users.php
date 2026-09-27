<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Stub;

use App\Entity\User;
use ReflectionProperty;

final class Users
{
    public static function withId(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
