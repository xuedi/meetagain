<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageInterface;
use App\Service\Activity\Messages\UnFollowedUser;
use App\Service\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class UnFollowedUserTest extends TestCase
{
    private RouterInterface $router;
    private ImageHtmlRenderer $imageService;

    public function setUp(): void
    {
        $this->router = $this->createStub(RouterInterface::class);
        $this->imageService = $this->createStub(ImageHtmlRenderer::class);
    }

    public function testCanBuild(): void
    {
        $userId = 42;
        $userName = 'testUser';
        $expectedText = 'Stopped following: testUser';
        $expectedHtml = 'Stopped following: testUser';

        $meta = ['user_id' => $userId];
        $userNames = [$userId => $userName];

        $subject = new UnFollowedUser();
        $subject->injectServices($this->router, $this->imageService, $meta, $userNames);

        // check returns
        $this->assertInstanceOf(MessageInterface::class, $subject->validate());
        $this->assertEquals(ActivityType::UnFollowedUser, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }

    public function testHandlesDeletedUser(): void
    {
        $userId = 42;
        $expectedText = 'Stopped following: [deleted]';
        $expectedHtml = 'Stopped following: [deleted]';

        $meta = ['user_id' => $userId];
        $userNames = []; // User no longer exists

        $subject = new UnFollowedUser();
        $subject->injectServices($this->router, $this->imageService, $meta, $userNames);

        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }

    public function testCanCatchMissingUserId(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'user_id' in meta in UnFollowedUser"));

        $subject = new UnFollowedUser();
        $subject->injectServices($this->router, $this->imageService, []);
        $subject->validate();
    }

    public function testCanCatchNonNumericUserId(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'user_id' has to be numeric in 'UnFollowedUser'"),
        );

        $subject = new UnFollowedUser();
        $subject->injectServices($this->router, $this->imageService, ['user_id' => 'not-a-number']);
        $subject->validate();
    }
}
