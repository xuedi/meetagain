<?php declare(strict_types=1);

namespace Tests\Functional\Service\Security;

use App\Service\Security\ContentSanitizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ContentSanitizerTest extends KernelTestCase
{
    private ContentSanitizer $sanitizer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->sanitizer = self::getContainer()->get(ContentSanitizer::class);
    }

    public function testPlainKeepsTextAndUnicode(): void
    {
        static::assertSame('Hello world - café 日本語 😀', $this->sanitizer->toPlainText('Hello world - café 日本語 😀'));
    }

    public function testPlainStripsTagsButKeepsText(): void
    {
        static::assertSame('bold and italic', $this->sanitizer->toPlainText('<b>bold</b> and <i>italic</i>'));
    }

    public function testPlainDropsScriptEntirely(): void
    {
        $result = $this->sanitizer->toPlainText('hi<script>alert(1)</script>there');

        static::assertStringNotContainsString('alert', $result);
        static::assertStringNotContainsString('<', $result);
    }

    public function testEscapeNeutralisesTagsLosslessly(): void
    {
        // Bug-report style content with angle brackets must survive verbatim.
        $result = $this->sanitizer->escape('shows <error>X</error> here');

        static::assertStringContainsString('X', $result);
        static::assertStringContainsString('&lt;error&gt;', $result);
        static::assertStringNotContainsString('<error>', $result);
    }

    public function testEscapeNeutralisesScript(): void
    {
        $result = $this->sanitizer->escape('<script>alert(document.cookie)</script>');

        static::assertStringNotContainsString('<script', $result);
        static::assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testBasicKeepsInlineEmphasis(): void
    {
        static::assertSame(
            '<b>bold</b> <strong>s</strong> <i>i</i> <em>e</em><br />next',
            $this->sanitizer->basic('<b>bold</b> <strong>s</strong> <i>i</i> <em>e</em><br>next'),
        );
    }

    public function testBasicDropsScript(): void
    {
        static::assertSame('<b>ok</b>', $this->sanitizer->basic('<b>ok</b><script>alert(document.cookie)</script>'));
    }

    public function testBasicStripsAttributes(): void
    {
        static::assertSame('<b>y</b>', $this->sanitizer->basic('<b onclick="steal()" class="x">y</b>'));
    }

    public function testBasicDropsDisallowedElementsAndScriptableUrls(): void
    {
        $result = $this->sanitizer->basic('<a href="javascript:alert(1)">link</a> <div onmouseover="x">block</div>');

        static::assertStringNotContainsString('<a', $result);
        static::assertStringNotContainsString('href', $result);
        static::assertStringNotContainsString('javascript', $result);
        static::assertStringNotContainsString('<div', $result);
        static::assertStringNotContainsString('onmouseover', $result);
    }
}
