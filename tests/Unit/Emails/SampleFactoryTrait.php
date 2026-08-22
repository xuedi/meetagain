<?php declare(strict_types=1);

namespace Tests\Unit\Emails;

use App\Emails\MockSampleFactory;
use App\Service\Http\RequestHostResolver;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\Translation\TranslatorInterface;

trait SampleFactoryTrait
{
    private function mockSampleFactory(
        string $host = 'https://meetagain.local',
        string $url = 'meetagain.local',
    ): MockSampleFactory {
        $resolver = $this->createStub(RequestHostResolver::class);
        $resolver->method('getSchemeAndHost')->willReturn($host);
        $resolver->method('getHost')->willReturn($url);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new MockSampleFactory($resolver, $translator, new MockClock());
    }
}
