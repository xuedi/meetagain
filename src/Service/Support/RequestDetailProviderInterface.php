<?php declare(strict_types=1);

namespace App\Service\Support;

use App\Entity\SupportRequest;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface RequestDetailProviderInterface
{
    /**
     * Read-only lines for the admin detail page of a request; every provider's lines are shown.
     *
     * @return array<string, string> translated label => plain-text value
     */
    public function getDetails(SupportRequest $request): array;
}
