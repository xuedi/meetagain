<?php declare(strict_types=1);

namespace App\Publisher\Frontpage;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AutoconfigureTag]
interface FrontpageProviderInterface
{
    public function render(Request $request): ?Response;
}
