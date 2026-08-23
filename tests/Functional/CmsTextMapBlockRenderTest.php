<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Entity\BlockType\TextMap;
use App\Entity\Session\Consent;
use App\Enum\ConsentType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;

class CmsTextMapBlockRenderTest extends KernelTestCase
{
    private const array PAYLOAD = [
        'title' => 'How to find us',
        'content' => '<p>Ring the bell marked Go Club.</p>',
        'latitude' => '52.5321',
        'longitude' => '13.3799',
        'zoom' => '16',
        'markerLabel' => 'Weiqi Cafe Berlin',
        'mapPosition' => 'right',
        'mapWidth' => 'half',
        'mapHeight' => 'large',
    ];

    public function testMapBlockRendersCanvasFallbackAndCoordinates(): void
    {
        // Arrange
        $this->bootWithRequest();

        // Act
        $html = $this->renderBlock(self::PAYLOAD);

        // Assert
        static::assertStringContainsString('data-cms-map', $html);
        static::assertStringContainsString('data-lat="52.5321"', $html);
        static::assertStringContainsString('data-lng="13.3799"', $html);
        static::assertStringContainsString('data-zoom="16"', $html);
        static::assertStringContainsString('data-osm-height="480px"', $html);
        static::assertStringContainsString('data-marker-label="Weiqi Cafe Berlin"', $html);
        static::assertStringContainsString('data-cms-map-fallback', $html);
        static::assertStringContainsString('openstreetmap.org', $html);
        static::assertStringContainsString('column is-6', $html);
    }

    public function testMarkupDoesNotVaryWithOsmConsent(): void
    {
        // Arrange
        $this->bootWithRequest(ConsentType::Granted);
        $granted = $this->renderBlock(self::PAYLOAD);
        self::ensureKernelShutdown();
        $this->bootWithRequest(ConsentType::Denied);

        // Act
        $denied = $this->renderBlock(self::PAYLOAD);

        // Assert
        static::assertSame(
            $granted,
            $denied,
            'The CMS body is cached and shared, so a map block must render identically for every visitor',
        );
    }

    public function testBlockWithoutCoordinatesRendersTextOnly(): void
    {
        // Arrange
        $this->bootWithRequest();

        // Act
        $html = $this->renderBlock(['content' => '<p>Directions follow soon.</p>']);

        // Assert
        static::assertStringContainsString('Directions follow soon.', $html);
        static::assertStringNotContainsString('data-cms-map', $html);
    }

    public function testStackedPositionDropsTheColumnLayout(): void
    {
        // Arrange
        $this->bootWithRequest();

        // Act
        $html = $this->renderBlock([...self::PAYLOAD, 'mapPosition' => 'above']);

        // Assert
        static::assertStringContainsString('data-cms-map', $html);
        static::assertStringNotContainsString('columns is-vcentered', $html);
    }

    private function bootWithRequest(?ConsentType $osmConsent = null): void
    {
        self::bootKernel();

        $request = Request::create('/en/directions');
        $request->setLocale('en');

        if ($osmConsent !== null) {
            $session = new Session(new MockArraySessionStorage());
            $consent = new Consent();
            $consent->setOsm($osmConsent);
            $consent->save($session);
            $request->setSession($session);
        }

        $requestStack = self::getContainer()->get('request_stack');
        static::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push($request);
    }

    private function renderBlock(array $payload): string
    {
        $twig = self::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig->render('cms/blocks/TextMap.html.twig', ['block' => TextMap::fromJson($payload)]);
    }
}
