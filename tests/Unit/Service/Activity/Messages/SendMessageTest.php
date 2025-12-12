<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\Messages\SendMessage;
use App\Service\ImageService;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class SendMessageTest extends TestCase
{
    private MockObject|RouterInterface $router;
    private MockObject|ImageService $imageService;

    public function setUp(): void
    {
        $this->router = $this->createStub(RouterInterface::class);
        $this->imageService = $this->createStub(ImageService::class);
    }

    public function testCanBuild(): void
    {
        $userId = 42;
        $userName = 'testUser';
        $expectedText = 'Send a message to: testUser';
        $expectedHtml = 'Send a message to: testUser';

        $meta = ['user_id' => $userId];
        $userNames = [$userId => $userName];

        $subject = new SendMessage();
        $subject->injectServices($this->router, $this->imageService, $meta, $userNames);

        // check returns
        $this->assertTrue($subject->validate());
        $this->assertEquals(ActivityType::SendMessage, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }

    public function testCanCatchMissingUserId(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'user_id' in meta in SendMessage"));

        $subject = new SendMessage();
        $subject->injectServices($this->router, $this->imageService, []);
        $subject->validate();
    }

    public function testCanCatchNonNumericUserId(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'user_id' has to be numeric in 'SendMessage'"),
        );

        $subject = new SendMessage();
        $subject->injectServices($this->router, $this->imageService, ['user_id' => 'not-a-number']);
        $subject->validate();
    }
}
