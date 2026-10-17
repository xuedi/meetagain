<?php declare(strict_types=1);

namespace App\Theme;

use App\Form\ThemeColorsType;
use App\Publisher\PluginSettings\DescriptorInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;

final readonly class Descriptor implements DescriptorInterface
{
    public const string KEY = 'theme';

    private const array SCSS_VARIABLES = [
        'primary' => 'color_primary',
        'link' => 'color_link',
        'info' => 'color_info',
        'success' => 'color_success',
        'warning' => 'color_warning',
        'danger' => 'color_danger',
        'text-grey' => 'color_text_grey',
        'text-grey-light' => 'color_text_grey_light',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/assets/styles')]
        private string $stylesDir,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return self::KEY;
    }

    #[Override]
    public function getPluginKey(): string
    {
        return 'core';
    }

    #[Override]
    public function isScopable(): bool
    {
        return false;
    }

    #[Override]
    public function getTitleKey(): string
    {
        return 'admin_system_theme.heading_colors';
    }

    #[Override]
    public function getIntroKey(): ?string
    {
        return null;
    }

    #[Override]
    public function getFormType(): string
    {
        return ThemeColorsType::class;
    }

    #[Override]
    public function getFormOptions(object $data): array
    {
        return [];
    }

    #[Override]
    public function createDefault(): object
    {
        return $this->compiledBaseline();
    }

    #[Override]
    public function applyForm(object $data, FormInterface $form): void {}

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    public function compiledBaseline(): Config
    {
        $path = is_file($this->stylesDir . '/_config.scss') ? $this->stylesDir . '/_config.scss' : $this->stylesDir . '/_config.scss.dist';

        return $this->parse($path);
    }

    public function distributedDefaults(): Config
    {
        return $this->parse($this->stylesDir . '/_config.scss.dist');
    }

    private function parse(string $path): Config
    {
        $content = is_file($path) ? (string) file_get_contents($path) : '';

        $colors = [];
        foreach (self::SCSS_VARIABLES as $variable => $key) {
            $match = [];
            if (preg_match('/\$' . preg_quote($variable, '/') . '\s*:\s*(#[0-9a-fA-F]{6})\s*;/', $content, $match) === 1) {
                $colors[$key] = $match[1];
            }
        }

        return Config::fromArray($colors);
    }
}
