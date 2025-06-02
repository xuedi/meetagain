<?php declare(strict_types=1);

namespace App\Service;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

readonly class CaptchaService
{
    const int LENGTH = 4;
    const string FONT = __DIR__ . '/../../assets/fonts/captcha.ttf';
    const string VALID_CHARACTERS = 'abcdefghjklmnpqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ';

    private SessionInterface $session;

    public function setSession(SessionInterface $session): void
    {
        $this->session = $session;
    }

    public function generate(): string
    {
        $image = $this->session->get('captcha_image' . $this->session->getId(), null);
        if($image !== null) {
            return $image;
        }
        $code = '';
        foreach (range(0, self::LENGTH - 1) as $i) {
            $code .= self::VALID_CHARACTERS[random_int(0, strlen(self::VALID_CHARACTERS) - 1)];
        }
        $image = $this->generateImage($code);

        $this->session->set('captcha_text' . $this->session->getId(), $code);
        $this->session->set('captcha_image' . $this->session->getId(), $image);

        return $image;
    }

    public function isValidate(string $code): ?string
    {
        $code = strtolower($code);
        $expected = strtolower($this->session->get('captcha_text' . $this->session->getId(), null));
        if ($expected !== $code) {
            return sprintf("Wrong captcha code, got '%s' but expected '%s'", $code, $expected);
        }
        return null;
    }

    private function generateImage(string $code): string
    {
        $image = new Imagick();
        $image->newImage(100, 60, new ImagickPixel("grey"));
        $image->setImageFormat('png');

        $x = 8;
        $baseY = 38;
        $size = 25;
        foreach (str_split($code) as $char) {
            $angle = random_int(-20, 20);
            $y = $baseY + random_int(-6, 6);
            $draw = new ImagickDraw();
            $draw->setFont(self::FONT);
            $draw->setFontSize($size + random_int(-4, 6));;
            $draw->setFillColor(sprintf('#%s0%s0%s0', random_int(0, 9), random_int(0, 9), random_int(0, 9)));;
            $image->annotateImage($draw, $x, $y, $angle, $char);
            $x += 20 + random_int(-4, 4);
        }

        return base64_encode($image->getimageblob());
    }

    public function reset(): void
    {
        $this->session->remove('captcha_text' . $this->session->getId());
        $this->session->remove('captcha_image' . $this->session->getId());
    }
}
