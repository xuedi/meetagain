<?php declare(strict_types=1);

namespace App\Filter\Email;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class AudienceFilterService
{
    /**
     * @param iterable<AudienceFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(AudienceFilterInterface::class)]
        private iterable $filters,
    ) {}

    /**
     * @param list<User> $recipients
     * @return list<User>
     */
    public function installationWideAudience(array $recipients): array
    {
        foreach ($this->filters as $filter) {
            $recipients = array_values($filter->filterInstallationWideAudience($recipients));
            if ($recipients === []) {
                return [];
            }
        }

        return $recipients;
    }
}
