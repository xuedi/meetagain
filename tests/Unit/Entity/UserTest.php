<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Activity;
use App\Entity\Event;
use App\Entity\Image;
use App\Entity\UserStatus;
use App\Tests\Unit\Entity\Stubs\UserStub;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private UserStub $user;

    protected function setUp(): void
    {
        $this->user = new UserStub();
        $this->user->setId(1); // stubbyStub
    }

    public function testEmailGetterAndSetter(): void
    {
        $email = 'test@example.com';
        $this->user->setEmail($email);

        $this->assertEquals($email, $this->user->getEmail());
        $this->assertEquals($email, $this->user->getUserIdentifier());
    }

    public function testRolesGetterAndSetter(): void
    {
        $roles = ['ROLE_USER', 'ROLE_ADMIN'];
        $this->user->setRoles($roles);

        $this->assertEquals($roles, $this->user->getRoles());
    }

    public function testPasswordGetterAndSetter(): void
    {
        $password = 'hashedPassword123';
        $this->user->setPassword($password);

        $this->assertEquals($password, $this->user->getPassword());
    }

    public function testNameGetterAndSetter(): void
    {
        $name = 'John Doe';
        $this->user->setName($name);

        $this->assertEquals($name, $this->user->getName());
    }

    public function testCreatedAtGetterAndSetter(): void
    {
        $date = new DateTimeImmutable();
        $this->user->setCreatedAt($date);

        $this->assertEquals($date, $this->user->getCreatedAt());
    }

    public function testLastLoginGetterAndSetter(): void
    {
        $date = new DateTime();
        $this->user->setLastLogin($date);

        $this->assertEquals($date, $this->user->getLastLogin());
    }

    public function testLocaleGetterAndSetter(): void
    {
        $locale = 'fr';
        $this->user->setLocale($locale);

        $this->assertEquals($locale, $this->user->getLocale());
    }

    public function testStatusGetterAndSetter(): void
    {
        $status = UserStatus::Active;
        $this->user->setStatus($status);

        $this->assertEquals($status, $this->user->getStatus());
    }

    public function testFollowingLogic(): void
    {
        // Setup
        $user = new UserStub();
        $user->setId(2); // Assuming there's a setId method

        // Test adding following
        $this->user->addFollowing($user);
        $this->assertTrue($this->user->getFollowing()->contains($user));

        // Test duplicate adding (should not duplicate)
        $this->user->addFollowing($user);
        $this->assertCount(1, $this->user->getFollowing());

        // Test self-following prevention
        $this->user->addFollowing($this->user);
        $this->assertCount(1, $this->user->getFollowing());;
        $this->assertFalse($this->user->getFollowing()->contains($this->user));

        // Test removing following
        $this->user->removeFollowing($user);
        $this->assertFalse($this->user->getFollowing()->contains($user));

        // Test removing non-existent following (should not throw)
        $this->user->removeFollowing($user);
    }

    public function testFollowerLogic(): void
    {
        // Setup
        $user = new UserStub();
        $user->setId(3); // Assuming there's a setId method

        // Test adding following
        $this->user->addFollower($user);
        $this->assertTrue($this->user->getFollowers()->contains($user));

        // Test duplicate adding (should not duplicate)
        $this->user->addFollower($user);
        $this->assertCount(1, $this->user->getFollowers());

        // Test self-following prevention
        $this->user->addFollower($this->user);
        $this->assertCount(1, $this->user->getFollowers());
        $this->assertFalse($this->user->getFollowers()->contains($this->user));

        // Test removing following
        $this->user->removeFollower($user);
        $this->assertFalse($this->user->getFollowers()->contains($user));

        // Test removing non-existent following (should not throw)
        $this->user->removeFollower($user);
    }

    public function testVerificationStatus(): void
    {
        $this->assertFalse($this->user->isVerified());

        $this->user->setVerified(true);
        $this->assertTrue($this->user->isVerified());
    }

    public function testActivityManagement(): void
    {
        $activity = new Activity();

        $this->user->addActivity($activity);
        $this->assertTrue($this->user->getActivities()->contains($activity));

        $this->user->removeActivity($activity);
        $this->assertFalse($this->user->getActivities()->contains($activity));
    }

    public function testEventRsvpManagement(): void
    {
        $event = new Event();

        $this->user->addRsvpEvent($event);
        $this->assertTrue($this->user->getRsvpEvents()->contains($event));

        $this->user->removeRsvpEvent($event);
        $this->assertFalse($this->user->getRsvpEvents()->contains($event));
    }

    public function testImageGetterAndSetter(): void
    {
        $image = new Image();
        $this->user->setImage($image);

        $this->assertSame($image, $this->user->getImage());
    }

    public function testBioGetterAndSetter(): void
    {
        $bio = 'Test biography';
        $this->user->setBio($bio);

        $this->assertEquals($bio, $this->user->getBio());
    }

    public function testRegcodeGetterAndSetter(): void
    {
        $code = 'RegHash';
        $this->user->setRegcode($code);

        $this->assertEquals($code, $this->user->getRegcode());
    }
}
