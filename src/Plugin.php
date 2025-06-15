<?php declare(strict_types=1);

namespace App;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface Plugin
{
    public function getIdent(): string;
    public function getName(): string;
    public function getVersion(): string;
    public function getDescription(): string;
    public function install(): void;
    public function uninstall(): void;
    public function handleRoute(string $url): ?string;
}
