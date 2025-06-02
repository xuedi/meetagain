<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\CaptchaService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CaptchaServiceTest extends TestCase
{
    private MockObject|SessionInterface $sessionMock;
    private CaptchaService $subject;

    protected function setUp(): void
    {
        $this->sessionMock = $this->createMock(SessionInterface::class);
        $this->subject = new CaptchaService();
    }
}
