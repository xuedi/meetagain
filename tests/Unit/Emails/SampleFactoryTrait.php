<?php declare(strict_types=1);

namespace Tests\Unit\Emails;

use App\Emails\MockSampleFactory;
use App\Service\Http\RequestHostResolver;
use App\Service\Security\ContentSanitizer;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Contracts\Translation\TranslatorInterface;

trait SampleFactoryTrait
{
    private function mockSampleFactory(string $host = 'https://meetagain.local', string $url = 'meetagain.local'): MockSampleFactory
    {
        $resolver = $this->createStub(RequestHostResolver::class);
        $resolver->method('getSchemeAndHost')->willReturn($host);
        $resolver->method('getHost')->willReturn($url);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new MockSampleFactory($resolver, $translator, new MockClock());
    }

    private function contentSanitizer(): ContentSanitizer
    {
        $basic = new HtmlSanitizerConfig()
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('br');

        return new ContentSanitizer(new HtmlSanitizer($basic), new HtmlSanitizer(new HtmlSanitizerConfig()->allowSafeElements()));
    }
}
