<?php declare(strict_types=1);

namespace Plugin\Dishes;

use App\Plugin;

class Manifest implements Plugin
{
    public function getIdent(): string
    {
        return 'dishes';
    }

    public function getName(): string
    {
        return 'Dishes';
    }

    public function getVersion(): string
    {
        return '0.1';
    }

    public function getDescription(): string
    {
        return 'This allows creating and social interaction about everything food.';
    }

    public function install(): void
    {
        // TODO: Implement install() method.
    }

    public function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }

    public function handleRoute(string $url): ?string
    {
        return match ($url) {
            'dishes' => 'dishes',
            default => null,
        };
    }
}