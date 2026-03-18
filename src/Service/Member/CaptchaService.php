<?php declare(strict_types=1);

namespace App\Service\Member;

use DateTimeImmutable;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

readonly class CaptchaService
{
    private const int MAX_ATTEMPTS = 3;
    private const int MAX_REFRESHES = 7;
    private const string CHARS = 'abcdefghjklmnpqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ';

    public function __construct(
        private RequestStack $requestStack,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }

    public function generate(): string
    {
        $session = $this->getSession();
        $image = $session->get('captcha_image');
        if ($image !== null) {
            return $image;
        }

        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
        }
        $image = $this->generateImage($code);

        $refresh = $session->get('captcha_refresh', []);
        $refresh[] = new DateTimeImmutable();

        $session->set('captcha_refresh', $refresh);
        $session->set('captcha_text', $code);
        $session->set('captcha_image', $image);

        return $image;
    }

    public function isValid(string $code): ?string
    {
        $session = $this->getSession();
        $expected = strtolower((string) $session->get('captcha_text'));

        if (!hash_equals($expected, strtolower($code))) {
            $attempts = (int) $session->get('captcha_attempts', 0) + 1;
            $session->set('captcha_attempts', $attempts);

            if ($attempts >= self::MAX_ATTEMPTS) {
                $session->remove('captcha_text');
                $session->remove('captcha_image');
                $session->remove('captcha_attempts');
            }

            return 'Wrong captcha code, please try again.';
        }

        $session->remove('captcha_attempts');

        return null;
    }

    public function reset(): void
    {
        $session = $this->getSession();
        if ($this->getRefreshCount() >= self::MAX_REFRESHES) {
            return;
        }
        $session->remove('captcha_text');
        $session->remove('captcha_image');
        $session->remove('captcha_attempts');
    }

    public function getRefreshCount(): int
    {
        $session = $this->getSession();
        $refresh = $session->get('captcha_refresh', []);
        foreach ($refresh as $key => $value) {
            if ($value->modify('+1 minute') < new DateTimeImmutable()) {
                unset($refresh[$key]);
            }
        }
        $session->set('captcha_refresh', $refresh);

        return count($refresh);
    }

    public function getRefreshTime(): int
    {
        $session = $this->getSession();
        $refresh = $session->get('captcha_refresh', []);
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

    private function generateImage(string $code): string
    {
        $image = new Imagick();
        $image->newImage(100, 60, new ImagickPixel('grey'));
        $image->setImageFormat('png');

        // Add noise lines
        for ($i = 0; $i < 4; $i++) {
            $draw = new ImagickDraw();
            $draw->setStrokeColor(sprintf(
                'rgb(%d,%d,%d)',
                random_int(130, 190),
                random_int(130, 190),
                random_int(130, 190),
            ));
            $draw->setStrokeWidth(1);
            $draw->line(random_int(0, 100), random_int(0, 60), random_int(0, 100), random_int(0, 60));
            $image->drawImage($draw);
        }

        // Add noise dots
        for ($i = 0; $i < 40; $i++) {
            $draw = new ImagickDraw();
            $draw->setFillColor(sprintf(
                'rgb(%d,%d,%d)',
                random_int(80, 180),
                random_int(80, 180),
                random_int(80, 180),
            ));
            $draw->point(random_int(0, 100), random_int(0, 60));
            $image->drawImage($draw);
        }

        // Draw characters
        $x = 8;
        $baseY = 38;
        $size = 25;
        foreach (str_split($code) as $char) {
            $angle = random_int(-20, 20);
            $y = $baseY + random_int(-6, 6);
            $draw = new ImagickDraw();
            $draw->setFont($this->projectDir . '/public/fonts/captcha.ttf');
            $draw->setFontSize($size + random_int(-4, 6));
            $draw->setFillColor(sprintf('rgb(%d,%d,%d)', random_int(0, 80), random_int(0, 80), random_int(0, 80)));
            $image->annotateImage($draw, $x, $y, $angle, $char);
            $x += 20 + random_int(-4, 4);
        }

        return base64_encode($image->getimageblob());
    }
}
