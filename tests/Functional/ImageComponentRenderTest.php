<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Entity\Image;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class ImageComponentRenderTest extends KernelTestCase
{
    #[DataProvider('provideSizeCases')]
    public function testTheComponentEmitsOnlyTheAxesTheSizeNames(string $size, string $expectedAttributes): void
    {
        // Arrange
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $request = Request::create('/en/anything');
        $request->setLocale('en');
        $requestStack = self::getContainer()->get('request_stack');
        static::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push($request);

        $image = new Image();
        $image->setHash('deadbeef');

        // Act
        $html = $twig->render('_components/image.html.twig', ['image' => $image, 'size' => $size]);

        // Assert
        static::assertStringContainsString('src="/images/thumbnails/deadbeef_' . $size . '.webp"', $html);
        static::assertStringContainsString($expectedAttributes, $html);
    }

    public static function provideSizeCases(): iterable
    {
        yield 'fixed box emits both axes' => ['400x400', 'width="400" height="400"'];
        yield 'free width emits the height only' => ['h120', 'height="120" /'];
        yield 'free height emits the width only' => ['w350', 'width="350" /'];
    }

    public function testFreeAxisAttributeIsAbsentEntirely(): void
    {
        // Arrange
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $request = Request::create('/en/anything');
        $request->setLocale('en');
        $requestStack = self::getContainer()->get('request_stack');
        static::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push($request);

        $image = new Image();
        $image->setHash('deadbeef');

        // Act
        $html = $twig->render('_components/image.html.twig', ['image' => $image, 'size' => 'h120']);

        // Assert
        static::assertStringNotContainsString('width=', $html);
    }
}
