<?php declare(strict_types=1);

namespace App\Service\Support;

use App\Entity\SupportRequest;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface RecipientProviderInterface
{
    /**
     * Claims requests by id; a request left out or claimed with no users is handed on, the admins are the last resort.
     *
     * @param list<SupportRequest> $requests
     * @return array<int, User[]> request id => recipients
     */
    public function getRecipients(array $requests): array;
}
