<?php declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

readonly class CaptchaService
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }

    public function generate(): string
    {
        $session = $this->getSession();
        $image = $session->get('captcha_image' . $session->getId());
        if ($image !== null) {
            return $image;
        }

        $code = '';
        $length = 4;
        $validCharacters = 'abcdefghjklmnpqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ';
        foreach (range(0, $length - 1) as $i) {
            $code .= $validCharacters[random_int(0, strlen($validCharacters) - 1)];
        }
        $image = $this->generateImage($code);

        $refresh = $session->get('captcha_refresh' . $session->getId(), []);
        $refresh[] = new DateTimeImmutable();

        $session->set('captcha_refresh' . $session->getId(), $refresh);
        $session->set('captcha_text' . $session->getId(), $code);
        $session->set('captcha_image' . $session->getId(), $image);

        return $image;
    }

    public function isValid(string $code): ?string
    {
        $session = $this->getSession();
        $code = strtolower($code);
        $expected = strtolower((string) $session->get('captcha_text' . $session->getId()));
        if ($expected !== $code) {
            return sprintf("Wrong captcha code, got '%s' but expected '%s'", $code, $expected);
        }

        return null;
    }

    private function generateImage(string $code): string
    {
        $image = new Imagick();
        $image->newImage(100, 60, new ImagickPixel('grey'));
        $image->setImageFormat('png');

        $x = 8;
        $baseY = 38;
        $size = 25;
        foreach (str_split($code) as $char) {
            $angle = random_int(-20, 20);
            $y = $baseY + random_int(-6, 6);
            $draw = new ImagickDraw();
            $draw->setFont(__DIR__ . '/../../public/fonts/captcha.ttf');
            $draw->setFontSize($size + random_int(-4, 6));

            $draw->setFillColor(sprintf('#%s0%s0%s0', random_int(0, 9), random_int(0, 9), random_int(0, 9)));

            $image->annotateImage($draw, $x, $y, $angle, $char);
            $x += 20 + random_int(-4, 4);
        }

        return base64_encode($image->getimageblob());
    }

    public function reset(): void
    {
        $session = $this->getSession();
        if ($this->getRefreshCount() >= 7) {
            return;
        }
        $session->remove('captcha_text' . $session->getId());
        $session->remove('captcha_image' . $session->getId());
    }

    public function getRefreshCount(): int
    {
        $session = $this->getSession();
        $refresh = $session->get('captcha_refresh' . $session->getId(), []);
        foreach ($refresh as $key => $value) {
            if ($value->modify('+1 minute') < new DateTimeImmutable()) {
                unset($refresh[$key]);
            }
        }
        $session->set('captcha_refresh' . $session->getId(), $refresh);

        return count($refresh);
    }

    public function getRefreshTime(): int
    {
        $session = $this->getSession();
        $refresh = $session->get('captcha_refresh' . $session->getId(), []);
        $now = new DateTimeImmutable();
        $minSeconds = PHP_INT_MAX;

        foreach ($refresh as $value) {
            $expireTime = $value->modify('+1 minute');
            $seconds = $expireTime->getTimestamp() - $now->getTimestamp();
            if ($seconds > 0 && $seconds < $minSeconds) {
                $minSeconds = $seconds;
            }
        }

        return $minSeconds === PHP_INT_MAX ? 0 : $minSeconds;
    }
}
