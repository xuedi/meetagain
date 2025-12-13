<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageInterface;
use App\Service\Activity\Messages\RegistrationEmailConfirmed;
use App\Service\ImageService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class RegistrationEmailConfirmedTest extends TestCase
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
        $expectedText = 'User confirmed Email';
        $expectedHtml = 'User confirmed Email';

        $subject = new RegistrationEmailConfirmed();
        $subject->injectServices($this->router, $this->imageService);

        // check returns
        $this->assertInstanceOf(MessageInterface::class, $subject->validate());
        $this->assertEquals(ActivityType::RegistrationEmailConfirmed, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }
}
