<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\SafeHtmlExtension;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SafeHtmlExtensionTest extends TestCase
{
    private SafeHtmlExtension $subject;

    protected function setUp(): void
    {
        $this->subject = new SafeHtmlExtension();
    }

    public function testGetFiltersReturnsSafeHtmlFilter(): void
    {
        $filters = $this->subject->getFilters();

        static::assertCount(1, $filters);
        static::assertSame('safe_html', $filters[0]->getName());
    }

    #[DataProvider('allowedTagsProvider')]
    public function testAllowedTagsPassThrough(string $input, string $expected): void
    {
        static::assertSame($expected, $this->subject->safeHtml($input));
    }

    public static function allowedTagsProvider(): Generator
    {
        yield 'b tag' => ['<b>bold</b>', '<b>bold</b>'];
        yield 'strong tag' => ['<strong>bold</strong>', '<strong>bold</strong>'];
        yield 'em tag' => ['<em>italic</em>', '<em>italic</em>'];
        yield 'i tag' => ['<i>italic</i>', '<i>italic</i>'];
        yield 'u tag' => ['<u>under</u>', '<u>under</u>'];
        yield 'p tag' => ['<p>para</p>', '<p>para</p>'];
        yield 'br tag' => ['line<br>break', 'line<br>break'];
        yield 'self-closing br' => ['line<br/>break', 'line<br/>break'];
        yield 'br with space' => ['line<br />break', 'line<br />break'];
        yield 'nested allowed' => ['<b><em>both</em></b>', '<b><em>both</em></b>'];
        yield 'plain text' => ['Hello world', 'Hello world'];
        yield 'empty string' => ['', ''];
    }

    #[DataProvider('attributeStrippingProvider')]
    public function testAttributesAreStrippedFromAllowedTags(string $input, string $expected): void
    {
        static::assertSame($expected, $this->subject->safeHtml($input));
    }

    public static function attributeStrippingProvider(): Generator
    {
        yield 'class on b' => ['<b class="x">t</b>', '<b>t</b>'];
        yield 'style on strong' => ['<strong style="color:red">t</strong>', '<strong>t</strong>'];
        yield 'id on em' => ['<em id="foo">t</em>', '<em>t</em>'];
        yield 'onclick on b' => ['<b onclick="alert(1)">t</b>', '<b>t</b>'];
        yield 'onmouseover on strong' => ['<strong onmouseover="x()">t</strong>', '<strong>t</strong>'];
        yield 'data attribute on p' => ['<p data-x="y">t</p>', '<p>t</p>'];
        yield 'multiple attributes on b' => ['<b id="a" class="b" style="c">t</b>', '<b>t</b>'];
        yield 'href on b' => ['<b href="http://evil.com">t</b>', '<b>t</b>'];
        yield 'entity-encoded attribute' => ['<b &#111;nclick="alert(1)">x</b>', '<b>x</b>'];
        yield 'data URI in b href' => ['<b href="data:text/html,<x>">x</b>', '<b>x</b>'];
    }

    #[DataProvider('disallowedTagsProvider')]
    public function testDisallowedTagsAreStripped(string $input, string $expected): void
    {
        static::assertSame($expected, $this->subject->safeHtml($input));
    }

    public static function disallowedTagsProvider(): Generator
    {
        yield 'script tag — content kept as text' => ['<script>alert(1)</script>', 'alert(1)'];
        yield 'style tag — content kept as text' => ['<style>body{}</style>', 'body{}'];
        yield 'img tag' => ['<img src="x">', ''];
        yield 'a tag — text kept' => ['<a href="http://evil.com">x</a>', 'x'];
        yield 'iframe' => ['<iframe src="x"></iframe>', ''];
        yield 'object' => ['<object data="x"></object>', ''];
        yield 'embed' => ['<embed src="x">', ''];
        yield 'form' => ['<form action="x"><input></form>', ''];
        yield 'input' => ['<input type="text">', ''];
        yield 'button — text kept' => ['<button onclick="x()">b</button>', 'b'];
        yield 'h1 — text kept' => ['<h1>Heading</h1>', 'Heading'];
        yield 'div — text kept' => ['<div>content</div>', 'content'];
        yield 'span — text kept' => ['<span>text</span>', 'text'];
        yield 'table — text kept' => ['<table><tr><td>x</td></tr></table>', 'x'];
        yield 'svg — text kept' => ['<svg><path d="M0 0"/></svg>', ''];
        yield 'math — text kept' => ['<math><mi>x</mi></math>', 'x'];
        yield 'link tag' => ['<link rel="stylesheet" href="x">', ''];
        yield 'meta refresh' => ['<meta http-equiv="refresh" content="0;url=x">', ''];
        yield 'base tag' => ['<base href="http://evil.com">', ''];
    }

    #[DataProvider('scriptInjectionProvider')]
    public function testScriptInjectionIsBlocked(string $input, string $expectedOutput): void
    {
        $result = $this->subject->safeHtml($input);

        static::assertStringNotContainsStringIgnoringCase('<script', $result, "Script tag in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase('<iframe', $result, "iframe tag in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase('<object', $result, "object tag in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase('<embed', $result, "embed tag in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase('<form', $result, "form tag in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase(' onclick=', $result, "onclick attribute in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase(' onload=', $result, "onload attribute in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase(' onerror=', $result, "onerror attribute in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase(' onmouseover=', $result, "onmouseover attribute in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase(' onfocus=', $result, "onfocus attribute in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase(' ontouchstart=', $result, "ontouchstart attribute in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase(' onkeyup=', $result, "onkeyup attribute in output for: {$input}");
        // javascript: and vbscript: are only dangerous inside HTML attribute values
        static::assertStringNotContainsStringIgnoringCase('href="javascript:', $result, "javascript: href in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase("href='javascript:", $result, "javascript: href in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase('src="javascript:', $result, "javascript: src in output for: {$input}");
        static::assertStringNotContainsStringIgnoringCase('href="vbscript:', $result, "vbscript: href in output for: {$input}");

        static::assertSame($expectedOutput, $result);
    }

    public static function scriptInjectionProvider(): Generator
    {
        yield 'basic script tag' => ['<script>alert(1)</script>', 'alert(1)'];
        yield 'uppercase SCRIPT' => ['<SCRIPT>alert(1)</SCRIPT>', 'alert(1)'];
        yield 'mixed case Script' => ['<Script>alert(1)</Script>', 'alert(1)'];
        yield 'script with type attribute' => ['<script type="text/javascript">alert(1)</script>', 'alert(1)'];
        yield 'script with src, no content' => ['<script src="http://evil.com/x.js"></script>', ''];
        yield 'unclosed script tag' => ['<script>alert(1)', 'alert(1)'];
        yield 'script inside div' => ['<div><script>alert(1)</script></div>', 'alert(1)'];
        yield 'script after allowed tag' => ['<b>ok</b><script>alert(1)</script>', '<b>ok</b>alert(1)'];

        yield 'null byte in tag name' => ["<scr\x00ipt>alert(1)</scr\x00ipt>", 'alert(1)'];
        yield 'newline in tag name' => ["<scr\nipt>alert(1)</script>", 'alert(1)'];
        yield 'tab in tag name' => ["<scr\tipt>alert(1)</script>", 'alert(1)'];

        yield 'onclick on b' => ['<b onclick="alert(1)">x</b>', '<b>x</b>'];
        yield 'onmouseover on strong' => ['<strong onmouseover="alert(1)">x</strong>', '<strong>x</strong>'];
        yield 'onfocus on em' => ['<em onfocus="alert(1)">x</em>', '<em>x</em>'];
        yield 'onload on b' => ['<b onload="alert(1)">x</b>', '<b>x</b>'];
        yield 'onerror on b' => ['<b onerror="alert(1)">x</b>', '<b>x</b>'];
        yield 'onkeyup on p' => ['<p onkeyup="alert(1)">x</p>', '<p>x</p>'];
        yield 'ontouchstart on b' => ['<b ontouchstart="alert(1)">x</b>', '<b>x</b>'];
        yield 'entity-encoded attribute name' => ['<b &#111;nclick="alert(1)">x</b>', '<b>x</b>'];

        yield 'javascript: in href on b' => ['<b href="javascript:alert(1)">x</b>', '<b>x</b>'];
        yield 'JAVASCRIPT: uppercase' => ['<b href="JAVASCRIPT:alert(1)">x</b>', '<b>x</b>'];
        yield 'vbscript: in href on b' => ['<b href="vbscript:msgbox(1)">x</b>', '<b>x</b>'];
        yield 'data URI in b href' => ['<b href="data:text/html,<x>">x</b>', '<b>x</b>'];

        yield 'img onerror' => ['<img src="x" onerror="alert(1)">', ''];
        yield 'img javascript src' => ['<img src="javascript:alert(1)">', ''];

        yield 'svg with script' => ['<svg><script>alert(1)</script></svg>', 'alert(1)'];
        yield 'svg onload' => ['<svg onload="alert(1)">x</svg>', 'x'];

        yield 'iframe javascript src' => ['<iframe src="javascript:alert(1)"></iframe>', ''];
        yield 'object with data' => ['<object data="javascript:alert(1)"></object>', ''];
        yield 'embed with src' => ['<embed src="javascript:alert(1)">', ''];

        yield 'style tag with expression' => [
            '<style>body{background:url("javascript:alert(1)")}</style>',
            'body{background:url("javascript:alert(1)")}',
        ];
        yield 'style attribute on b' => ['<b style="background:url(javascript:alert(1))">x</b>', '<b>x</b>'];

        yield 'entity-encoded script (text)' => [
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            '&lt;script&gt;alert(1)&lt;/script&gt;',
        ];

        yield 'form with action' => ['<form action="http://evil.com"><input name="p"></form>', ''];
        yield 'button with onclick' => ['<button onclick="steal()">click</button>', 'click'];

        yield 'meta refresh redirect' => ['<meta http-equiv="refresh" content="0;url=http://evil.com">', ''];
        yield 'base href hijack' => ['<base href="http://evil.com">', ''];

        yield 'onclick + script inside b' => [
            '<b onclick="alert(1)" style="color:red"><script>steal()</script>click me</b>',
            '<b>steal()click me</b>',
        ];
    }

    #[DataProvider('newlineProvider')]
    public function testNewlinesAreConvertedToBr(string $input, string $expected): void
    {
        static::assertSame($expected, $this->subject->safeHtml($input));
    }

    public static function newlineProvider(): Generator
    {
        yield 'single newline' => ["line1\nline2", "line1<br />\nline2"];
        yield 'windows line ending' => ["line1\r\nline2", "line1<br />\r\nline2"];
        yield 'multiple newlines' => ["a\nb\nc", "a<br />\nb<br />\nc"];
        yield 'newline after allowed' => ["<b>bold</b>\ntext", "<b>bold</b><br />\ntext"];
    }

    #[DataProvider('realWorldProvider')]
    public function testRealWorldContent(string $input, string $expected): void
    {
        static::assertSame($expected, $this->subject->safeHtml($input));
    }

    public static function realWorldProvider(): Generator
    {
        yield 'typical event description with formatting' => [
            "Join us for a <b>Chinese-German</b> language exchange.\n\nAll levels welcome!",
            "Join us for a <b>Chinese-German</b> language exchange.<br />\n<br />\nAll levels welcome!",
        ];

        yield 'anchor stripped, text kept' => [
            'Visit <a href="http://evil.com">our site</a> for more.',
            'Visit our site for more.',
        ];

        yield 'mixed allowed and disallowed' => [
            '<b>Bold</b> and <script>alert(1)</script> text',
            '<b>Bold</b> and alert(1) text',
        ];

        yield 'bad actor combining onclick + script inside allowed tag' => [
            '<b onclick="alert(1)" style="color:red"><script>steal()</script>click me</b>',
            '<b>steal()click me</b>',
        ];

        yield 'arrow entities preserved (common in event descriptions)' => [
            '--&gt; Join us',
            '--&gt; Join us',
        ];

        yield 'description with bold and paragraph' => [
            "<p><b>Interested in Chinese/German?</b></p>\n<p>All levels welcome.</p>",
            "<p><b>Interested in Chinese/German?</b></p><br />\n<p>All levels welcome.</p>",
        ];
    }
}
