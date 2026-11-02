<?php declare(strict_types=1);

namespace Module\Email\Internal;

use App\Service\Config\LanguageService;
use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionParameter;
use Module\Task\Contract\ActionSection;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Override;

readonly class TemplateSeedAction implements ActionInterface
{
    public const string IDENTIFIER = 'email-template-seed';

    public function __construct(
        private EmailTemplateService $templates,
        private LanguageService $languages,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    #[Override]
    public function getSection(): ActionSection
    {
        return ActionSection::Defaults;
    }

    #[Override]
    public function getLabel(): string
    {
        return 'admin_email_seed.label';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'admin_email_seed.description';
    }

    #[Override]
    public function getParameters(): array
    {
        return [ActionParameter::flag('overwrite', 'admin_email_seed.param_overwrite')];
    }

    #[Override]
    public function getFacts(): array
    {
        return [
            'admin_email_seed.fact_stored' => (string) count($this->templates->listTemplates()),
            'admin_email_seed.fact_shipped' => (string) count($this->templates->getDefaultTemplates()),
        ];
    }

    #[Override]
    public function run(array $values, ?string $cursor = null): Result
    {
        $seeded = $this->seed((bool) ($values['overwrite'] ?? false));

        return new Result(Status::ok, sprintf(
            'Created %d templates and %d translations, overwrote %d translations.',
            count($seeded['templates']),
            count($seeded['translations']),
            count($seeded['overwritten']),
        ));
    }

    /**
     * @return array{templates: list<string>, translations: list<string>, overwritten: list<string>}
     */
    public function seed(bool $overwrite): array
    {
        return $this->templates->seed($this->languages->getEnabledCodes(), $overwrite);
    }
}
