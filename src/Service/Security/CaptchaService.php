<?php declare(strict_types=1);

namespace App\Service\Security;

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

    public function generate(string $formKey): string
    {
        $session = $this->getSession();
        $image = $session->get($this->sessionKey('image', $formKey));
        if (is_string($image)) {
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
        $session->set($this->sessionKey('text', $formKey), $code);
        $session->set($this->sessionKey('image', $formKey), $image);

        return $image;
    }

    public function isValid(string $formKey, string $code): ?string
    {
        $session = $this->getSession();
        $expected = $session->get($this->sessionKey('text', $formKey));

        if (!is_string($expected) || $expected === '') {
            return 'security.captcha_wrong';
        }

        if (!hash_equals(strtolower($expected), strtolower($code))) {
            $attemptKey = $this->sessionKey('attempts', $formKey);
            $attempts = (int) $session->get($attemptKey, 0) + 1;
            $session->set($attemptKey, $attempts);

            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->forget($formKey);
            }

            return 'security.captcha_wrong';
        }

        $this->forget($formKey);

        return null;
    }

    public function reset(string $formKey): void
    {
        if ($this->getRefreshCount() >= self::MAX_REFRESHES) {
            return;
        }

        $this->forget($formKey);
    }

    public function getRefreshCount(): int
    {
        return count($this->getRefreshExpiries());
    }

    public function getRefreshTime(): int
    {
        return $this->getRefreshExpiries()[0] ?? 0;
    }

    /** @return list<int> */
    public function getRefreshExpiries(): array
    {
        $session = $this->getSession();
        $now = new DateTimeImmutable()->getTimestamp();

        $active = [];
        $expiries = [];
        foreach ($session->get('captcha_refresh', []) as $refreshedAt) {
            $seconds = $refreshedAt->modify('+1 minute')->getTimestamp() - $now;
            if ($seconds <= 0) {
                continue;
            }

            $active[] = $refreshedAt;
            $expiries[] = $seconds;
        }
        $session->set('captcha_refresh', $active);
        sort($expiries);

        return $expiries;
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }

    private function forget(string $formKey): void
    {
        $session = $this->getSession();
        $session->remove($this->sessionKey('text', $formKey));
        $session->remove($this->sessionKey('image', $formKey));
        $session->remove($this->sessionKey('attempts', $formKey));
    }

    private function sessionKey(string $part, string $formKey): string
    {
        return 'captcha_' . $part . '_' . $formKey;
    }

    private function generateImage(string $code): string
    {
        $image = new Imagick();
        $image->newImage(100, 60, new ImagickPixel('grey'));
        $image->setImageFormat('png');

        for ($i = 0; $i < 4; $i++) {
            $draw = new ImagickDraw();
            $draw->setStrokeColor(sprintf('rgb(%d,%d,%d)', random_int(130, 190), random_int(130, 190), random_int(130, 190)));
            $draw->setStrokeWidth(1);
            $draw->line(random_int(0, 100), random_int(0, 60), random_int(0, 100), random_int(0, 60));
            $image->drawImage($draw);
        }

        for ($i = 0; $i < 40; $i++) {
            $draw = new ImagickDraw();
            $draw->setFillColor(sprintf('rgb(%d,%d,%d)', random_int(80, 180), random_int(80, 180), random_int(80, 180)));
            $draw->point(random_int(0, 100), random_int(0, 60));
            $image->drawImage($draw);
        }

        $x = 8;
        $baseY = 38;
        $size = 25;
        foreach (str_split($code) as $char) {
            $angle = random_int(-20, 20);
            $y = $baseY + random_int(-6, 6);
            $draw = new ImagickDraw();
            $draw->setFont($this->projectDir . '/assets/fonts/captcha.ttf');
            $draw->setFontSize($size + random_int(-4, 6));
            $draw->setFillColor(sprintf('rgb(%d,%d,%d)', random_int(0, 80), random_int(0, 80), random_int(0, 80)));
            $image->annotateImage($draw, $x, $y, $angle, $char);
            $x += 20 + random_int(-4, 4);
        }

        return base64_encode($image->getimageblob());
    }
}
