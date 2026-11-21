<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix\Hotfixes;

use App\DataHotfix\Hotfixes\ResetSupportResponseTemplate;
use App\Enum\EmailType;
use Module\Email\Contract\TemplatesInterface;
use PHPUnit\Framework\TestCase;

class ResetSupportResponseTemplateTest extends TestCase
{
    public function testResetsTheSupportResponseTemplate(): void
    {
        // Arrange
        $templates = $this->createMock(TemplatesInterface::class);
        $templates->expects(static::once())->method('resetToDefault')->with(EmailType::SupportResponse->value);

        // Act
        new ResetSupportResponseTemplate($templates)->execute();
    }
}
