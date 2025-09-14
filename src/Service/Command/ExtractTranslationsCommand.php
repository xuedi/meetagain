<?php declare(strict_types=1);

namespace App\Service\Command;

readonly class ExtractTranslationsCommand implements CommandInterface
{
    public function __construct(
        private string $locale,
    ) {
        //
    }

    public function getCommand(): string
    {
        return 'translation:extract';
    }

    public function getParameter(): array
    {
        return [
            'command' => $this->getCommand(),
            '--format' => 'php',
            '--force' => null,
            'locale' => $this->locale,
        ];
    }
}
