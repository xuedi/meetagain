<?php declare(strict_types=1);

namespace App\Service;

use Random\RandomException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

readonly class CaptchaService
{
    const int LENGTH = 4;
    const string VALID_CHARACTERS = 'abcdefghijklmnpqrstuvwxyz123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private SessionInterface $session;

    public function setSession(SessionInterface $session): void
    {
        $this->session = $session;
    }

    public function generate(): string
    {
        $code = $this->session->get('captcha_' . $this->session->getId(), null);
        if($code !== null) {
            return $code;
        }
        $code = '';
        try {
            foreach (range(0, self::LENGTH - 1) as $i) {
                $code .= self::VALID_CHARACTERS[random_int(0, strlen(self::VALID_CHARACTERS) - 1)];
            }
        } catch (RandomException $e) {
            $code = '0000';
        }
        $this->session->set('captcha_' . $this->session->getId(), $code);

        return $code;
    }

    public function isValidate(string $code): ?string
    {
        $expected = $this->session->get('captcha_' . $this->session->getId());
        if ($expected !== $code) {
            return sprintf('Wrong captcha code, got %s but expected %s', $code, $expected);
        }
        return null;
    }

    public function reset(): void
    {
        $this->session->remove('captcha_' . $this->session->getId());
    }
}
