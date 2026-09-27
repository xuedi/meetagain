<?php declare(strict_types=1);

namespace Module\Email\Tests\Stub;

use Module\Email\Contract\TemplateDefinition;
use Module\Email\Contract\TemplateProviderInterface;
use Override;

final readonly class TemplateProvider implements TemplateProviderInterface
{
    public const string SUBJECT = 'Hello {{name}}';
    public const string BODY = '<p>{{name}}</p><ul>{{itemsHtml}}</ul>';

    #[Override]
    public function getDefinitions(string $language): array
    {
        return [
            new TemplateDefinition(TriggeredEmail::IDENTIFIER, self::SUBJECT, self::BODY, ['name', 'itemsHtml'], ['itemsHtml']),
            new TemplateDefinition(ScheduledEmail::IDENTIFIER, self::SUBJECT, self::BODY, ['name', 'itemsHtml'], ['itemsHtml']),
        ];
    }
}
