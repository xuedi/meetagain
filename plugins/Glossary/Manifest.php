<?php declare(strict_types=1);

namespace Plugin\Glossary;

use App\Plugin;

class Manifest implements Plugin
{
    public function getIdent(): string
    {
        return 'glossary';
    }

    public function getName(): string
    {
        return 'Glossary';
    }

    public function getVersion(): string
    {
        return '0.1';
    }

    public function getDescription(): string
    {
        return 'This allows users to add and maintain a multilingual glossary.';
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
