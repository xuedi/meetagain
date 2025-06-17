<?php declare(strict_types=1);

namespace Plugin\Dishes;

use App\Plugin;
use Symfony\Component\HttpFoundation\Request;

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
}