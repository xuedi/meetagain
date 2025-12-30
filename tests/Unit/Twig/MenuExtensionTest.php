<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Entity\User;
use App\Service\MenuService;
use App\Twig\MenuExtension;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class MenuExtensionTest extends TestCase
{
    private Stub&RequestStack $requestStackStub;
    private Stub&MenuService $menuServiceStub;
    private Stub&Security $securityStub;
    private MenuExtension $subject;

    protected function setUp(): void
    {
        $this->requestStackStub = $this->createStub(RequestStack::class);
        $this->menuServiceStub = $this->createStub(MenuService::class);
        $this->securityStub = $this->createStub(Security::class);
        $this->subject = new MenuExtension(
            $this->requestStackStub,
            $this->menuServiceStub,
            $this->securityStub
        );
    }

    public function testGetFunctionsReturnsGetMenuFunction(): void
    {
        $functions = $this->subject->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('get_menu', $functions[0]->getName());
    }

    public function testGetMenuDelegatesToMenuService(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getLocale')->willReturn('de');
        $this->requestStackStub->method('getCurrentRequest')->willReturn($request);

        $user = $this->createStub(User::class);
        $this->securityStub->method('getUser')->willReturn($user);

        $expectedMenu = [
            ['label' => 'Home', 'url' => '/de/'],
            ['label' => 'Events', 'url' => '/de/events'],
        ];
        $this->menuServiceStub
            ->method('getMenuForContext')
            ->willReturn($expectedMenu);

        $result = $this->subject->getMenu('main');

        $this->assertSame($expectedMenu, $result);
    }

    public function testGetMenuUsesEnglishWhenNoRequest(): void
    {
        $this->requestStackStub->method('getCurrentRequest')->willReturn(null);
        $this->securityStub->method('getUser')->willReturn(null);

        $this->menuServiceStub
            ->method('getMenuForContext')
            ->willReturn([]);

        $result = $this->subject->getMenu('main');

        $this->assertSame([], $result);
    }
}
