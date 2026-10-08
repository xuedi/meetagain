<?php declare(strict_types=1);

namespace Module\Suggestion\Internal\Emails;

use App\ExtendedFilesystem;
use Module\Email\Contract\TemplateDefinition;
use Module\Email\Contract\TemplateProviderInterface;
use RuntimeException;

readonly class TemplateProvider implements TemplateProviderInterface
{
    private const string TEMPLATE_PATH = __DIR__ . '/../../../templates/email/defaults/';
    private const string FALLBACK_LANGUAGE = 'en';

    /** @var array<string, array<string, string>> */
    public const array SUBJECTS = [
        'en' => [
            Approved::IDENTIFIER => 'Your suggestion was approved',
            Rejected::IDENTIFIER => 'Your suggestion was not accepted',
        ],
        'de' => [
            Approved::IDENTIFIER => 'Dein Vorschlag wurde angenommen',
            Rejected::IDENTIFIER => 'Dein Vorschlag wurde nicht angenommen',
        ],
        'zh' => [
            Approved::IDENTIFIER => '你的提议已获批准',
            Rejected::IDENTIFIER => '你的提议未被采纳',
        ],
        'fr' => [
            Approved::IDENTIFIER => 'Ta suggestion a été acceptée',
            Rejected::IDENTIFIER => 'Ta suggestion n\'a pas été retenue',
        ],
        'es' => [
            Approved::IDENTIFIER => 'Tu sugerencia ha sido aprobada',
            Rejected::IDENTIFIER => 'Tu sugerencia no ha sido aceptada',
        ],
    ];

    public function __construct(
        private ExtendedFilesystem $fs,
    ) {}

    public function getDefinitions(string $language): array
    {
        $subjects = self::SUBJECTS[$language] ?? self::SUBJECTS[self::FALLBACK_LANGUAGE];

        $definitions = [];
        foreach ([Approved::IDENTIFIER, Rejected::IDENTIFIER] as $identifier) {
            $definitions[] = new TemplateDefinition(
                identifier: $identifier,
                subject: $subjects[$identifier],
                body: $this->loadBody($identifier, $language),
                variables: ResolvedAbstract::VARIABLES,
            );
        }

        return $definitions;
    }

    private function loadBody(string $identifier, string $language): string
    {
        foreach ([$language, self::FALLBACK_LANGUAGE] as $candidate) {
            $path = self::TEMPLATE_PATH . $candidate . '/' . $identifier . '.html';
            if ($this->fs->fileExists($path)) {
                return $this->fs->getFileContents($path) ?: '';
            }
        }

        throw new RuntimeException(sprintf('Email template file not found for "%s".', $identifier));
    }
}
