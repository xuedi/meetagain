<?php declare(strict_types=1);

namespace App\Service\Support;

use App\Entity\SupportRequest;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface RecipientProviderInterface
{
    /**
     * @return User[]|null null hands the request on; the admins are the last resort
     */
    public function getRecipients(SupportRequest $request): ?array;
}
