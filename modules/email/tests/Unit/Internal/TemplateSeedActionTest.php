<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Internal;

use App\Service\Config\LanguageService;
use Module\Email\Internal\EmailTemplateService;
use Module\Email\Internal\TemplateSeedAction;
use Module\Task\Contract\Status;
use PHPUnit\Framework\TestCase;

final class TemplateSeedActionTest extends TestCase
{
    public function testARunSeedsEveryEnabledLanguageAndCountsWhatItDid(): void
    {
        // Arrange
        $templates = $this->createMock(EmailTemplateService::class);
        $templates
            ->expects($this->once())
            ->method('seed')
            ->with(['en', 'de'], false)
            ->willReturn(['templates' => ['welcome'], 'translations' => ['welcome (en)', 'welcome (de)'], 'overwritten' => []]);

        // Act
        $result = $this->action($templates)->run([]);

        // Assert
        static::assertSame(Status::ok, $result->status);
        static::assertSame('Created 1 templates and 2 translations, overwrote 0 translations.', $result->message);
    }

    public function testTheOverwriteFlagReachesTheSeed(): void
    {
        // Arrange
        $templates = $this->createMock(EmailTemplateService::class);
        $templates
            ->expects($this->once())
            ->method('seed')
            ->with(['en', 'de'], true)
            ->willReturn(['templates' => [], 'translations' => [], 'overwritten' => ['welcome (en)']]);

        // Act
        $result = $this->action($templates)->run(['overwrite' => true]);

        // Assert
        static::assertSame('Created 0 templates and 0 translations, overwrote 1 translations.', $result->message);
    }

    private function action(EmailTemplateService $templates): TemplateSeedAction
    {
        $languages = $this->createStub(LanguageService::class);
        $languages->method('getEnabledCodes')->willReturn(['en', 'de']);

        return new TemplateSeedAction($templates, $languages);
    }
}
