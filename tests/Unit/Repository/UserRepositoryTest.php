<?php declare(strict_types=1);

namespace Tests\Unit\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class UserRepositoryTest extends TestCase
{
    private function createRepository(?QueryBuilder $qb = null): UserRepository
    {
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->name = User::class;

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($classMetadata);

        if ($qb !== null) {
            $em->method('createQueryBuilder')->willReturn($qb);
        }

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        return new UserRepository($registry);
    }

    private function createQueryBuilderMock(array $result): QueryBuilder
    {
        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn($result);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    private function setUserId(User $user, int $id): void
    {
        $reflection = new ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);
    }

    public function testGetUserNameListReturnsIdToNameMapping(): void
    {
        $queryResult = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
        ];

        $qb = $this->createQueryBuilderMock($queryResult);
        $repository = $this->createRepository($qb);

        $result = $repository->getUserNameList();

        $this->assertEquals([
            1 => 'Alice',
            2 => 'Bob',
            3 => 'Charlie',
        ], $result);
    }

    public function testGetUserNameListCachesResult(): void
    {
        $queryResult = [
            ['id' => 1, 'name' => 'Alice'],
        ];

        $qb = $this->createQueryBuilderMock($queryResult);
        $repository = $this->createRepository($qb);

        $result1 = $repository->getUserNameList();
        $result2 = $repository->getUserNameList();

        // Should return the exact same array (cached)
        $this->assertSame($result1, $result2);
    }

    public function testGetAllUserChoiceReturnsNameToIdMapping(): void
    {
        $queryResult = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $qb = $this->createQueryBuilderMock($queryResult);
        $repository = $this->createRepository($qb);

        $result = $repository->getAllUserChoice();

        $this->assertEquals([
            'Alice' => 1,
            'Bob' => 2,
        ], $result);
    }

    public function testResolveUserNameReturnsCorrectName(): void
    {
        $queryResult = [
            ['id' => 42, 'name' => 'TestUser'],
        ];

        $qb = $this->createQueryBuilderMock($queryResult);
        $repository = $this->createRepository($qb);

        $result = $repository->resolveUserName(42);

        $this->assertEquals('TestUser', $result);
    }

    public function testGetFollowersReturnsAllFollowersWhenNotExcludingFriends(): void
    {
        $repository = $this->createRepository();

        $user = new User();
        $this->setUserId($user, 1);

        $follower1 = new User();
        $this->setUserId($follower1, 2);
        $follower2 = new User();
        $this->setUserId($follower2, 3);

        $user->addFollower($follower1);
        $user->addFollower($follower2);

        $result = $repository->getFollowers($user, false);

        $this->assertCount(2, $result);
        $this->assertContains($follower1, $result);
        $this->assertContains($follower2, $result);
    }

    public function testGetFollowersExcludesFriendsWhenRequested(): void
    {
        $repository = $this->createRepository();

        $user = new User();
        $this->setUserId($user, 1);

        $followerOnly = new User();
        $this->setUserId($followerOnly, 2);
        $friend = new User();
        $this->setUserId($friend, 3);

        $user->addFollower($followerOnly);
        $user->addFollower($friend);
        $user->addFollowing($friend); // Make friend mutual

        $result = $repository->getFollowers($user, true);

        $this->assertCount(1, $result);
        $this->assertContains($followerOnly, $result);
        $this->assertNotContains($friend, $result);
    }

    public function testGetFriendsReturnsMutualConnections(): void
    {
        $repository = $this->createRepository();

        $user = new User();
        $this->setUserId($user, 1);

        $followerOnly = new User();
        $this->setUserId($followerOnly, 2);
        $followingOnly = new User();
        $this->setUserId($followingOnly, 3);
        $friend = new User();
        $this->setUserId($friend, 4);

        $user->addFollower($followerOnly);
        $user->addFollower($friend);
        $user->addFollowing($followingOnly);
        $user->addFollowing($friend);

        $result = $repository->getFriends($user);

        $this->assertCount(1, $result);
        $this->assertContains($friend, $result);
    }
}
