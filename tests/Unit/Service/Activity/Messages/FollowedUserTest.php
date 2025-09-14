<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\Messages\FollowedUser;
use App\Service\ImageService;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class FollowedUserTest extends TestCase
{
    private MockObject|RouterInterface $router;
    private MockObject|ImageService $imageService;

    public function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->imageService = $this->createMock(ImageService::class);
    }

    public function testCanBuild(): void
    {
        $userId = 42;
        $userName = 'testUser';
        $expectedText = 'Started following: testUser';
        $expectedHtml = 'Started following: testUser';

        $meta = ['user_id' => $userId];
        $userNames = [$userId => $userName];

        $subject = new FollowedUser();
        $subject->injectServices($this->router, $this->imageService, $meta, $userNames);

        // check returns
        $this->assertTrue($subject->validate());
        $this->assertEquals(ActivityType::FollowedUser, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }

    public function testCanCatchMissingUserId(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'user_id' in meta in FollowedUser"));

        $subject = new FollowedUser();
        $subject->injectServices($this->router, $this->imageService, []);
        $subject->validate();
    }

    public function testCanCatchNonNumericUserId(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'user_id' has to be numeric in 'FollowedUser'"),
        );

        $subject = new FollowedUser();
        $subject->injectServices($this->router, $this->imageService, ['user_id' => 'not-a-number']);
        $subject->validate();
    }
}
