<?php declare(strict_types=1);

namespace App\Service\Command;

readonly class EchoCommand implements CommandInterface
{
    public function __construct(private string $message)
    {

    }

    public function getCommand(): string
    {
        return 'app:echo';
    }

    public function getParameter(): array
    {
        return [
            'command' => $this->getCommand(),
            $this->message => null,
        ];
    }
}
