<?php declare(strict_types=1);

namespace Tests\Unit\Entity\Session;

use App\Entity\Session\Consent;
use App\Enum\ConsentType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class ConsentTest extends TestCase
{
    public function testExternalMediaSurvivesTheSession(): void
    {
        // Arrange
        $session = new Session(new MockArraySessionStorage());
        $consent = new Consent();
        $consent->setExternalMedia(ConsentType::Granted);

        // Act
        $consent->save($session);
        $restored = Consent::getBySession($session);

        // Assert
        static::assertSame(ConsentType::Granted, $restored->getExternalMedia());
        static::assertSame(ConsentType::Unknown, $restored->getOsm());
    }

    public function testASessionWrittenBeforeTheFlagExistedReadsAsUnknown(): void
    {
        // Arrange
        $session = new Session(new MockArraySessionStorage());
        $session->set(Consent::SESSION_NAME, json_encode([Consent::TYPE_COOKIES => 'granted', Consent::TYPE_OSM => 'granted']));

        // Act
        $consent = Consent::getBySession($session);

        // Assert
        static::assertSame(ConsentType::Granted, $consent->getOsm());
        static::assertSame(ConsentType::Unknown, $consent->getExternalMedia());
    }

    public function testExternalMediaTravelsAsItsOwnCookie(): void
    {
        // Arrange
        $consent = new Consent();
        $consent->setExternalMedia(ConsentType::Denied);

        // Act
        $cookies = $consent->getHtmlCookies();
        $restored = Consent::createByCookies(new InputBag([Consent::TYPE_EXTERNAL_MEDIA => 'granted']));

        // Assert
        $byName = array_combine(array_map(static fn(Cookie $cookie): string => $cookie->getName(), $cookies), $cookies);
        static::assertSame('denied', $byName[Consent::TYPE_EXTERNAL_MEDIA]->getValue());
        static::assertSame(ConsentType::Granted, $restored->getExternalMedia());
    }
}
