<?php declare(strict_types=1);

namespace App\Entity\Session;

use DateTime;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Throwable;

class Consent implements JsonSerializable
{
    public const string SESSION_NAME = 'session_key_consent';
    public const string TYPE_COOKIES = 'consent_cookies';
    public const string TYPE_OSM = 'consent_cookies_osm';

    public function __construct(
        private ?ConsentType $cookies = ConsentType::Unknown,
        private ?ConsentType $osm = ConsentType::Unknown,
    ) {
    }

    public function getCookies(): ConsentType
    {
        return $this->cookies;
    }

    public function setCookies(ConsentType $consentType): void
    {
        $this->cookies = $consentType;
    }

    public function getOsm(): ConsentType
    {
        return $this->osm;
    }

    public function setOsm(ConsentType $consentType): void
    {
        $this->osm = $consentType;
    }

    public function hasOsmConsent(): bool
    {
        return $this->osm === ConsentType::Granted;
    }

    public function getHtmlCookies(): array
    {
        $cookieExpires = new DateTime('+1 year');

        return [
            new Cookie(self::TYPE_OSM, $this->getOsm()->value, $cookieExpires),
            new Cookie(self::TYPE_COOKIES, $this->getCookies()->value, $cookieExpires),
        ];
    }

    public static function createByCookies(InputBag $cookies): self
    {
        $consent = new self();
        foreach ($cookies as $key => $value) {
            switch ($key) {
                case self::TYPE_OSM:
                    $consent->osm = ConsentType::from($value);
                    break;
                case self::TYPE_COOKIES:
                    $consent->cookies = ConsentType::from($value);
                    break;
            }
        }

        return $consent;
    }

    public static function getBySession(SessionInterface $session): self
    {
        $consent = new self();
        try {
            $json = $session->get(self::SESSION_NAME);
            $data = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
            $consent->setCookies(ConsentType::from($data[self::TYPE_COOKIES] ?? 'unknown'));
            $consent->setOsm(ConsentType::from($data[self::TYPE_OSM] ?? 'unknown'));
        } catch (Throwable) {
        }

        return $consent;
    }

    public function save(SessionInterface $session): void
    {
        $session->set(self::SESSION_NAME, json_encode($this->jsonSerialize()));
    }

    public function jsonSerialize(): array
    {
        return [
            self::TYPE_COOKIES => $this->getCookies()->value,
            self::TYPE_OSM => $this->getOsm()->value,
        ];
    }
}
