<?php declare(strict_types=1);

namespace App;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface Plugin
{
    public function getPluginKey(): string;
}
