<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Publisher\Noindex\NoindexProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

readonly class NoindexService
{
    /**
     * @param iterable<NoindexProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(NoindexProviderInterface::class)]
        private iterable $providers,
    ) {}

    public function shouldNoindex(Request $request): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->shouldNoindex($request)) {
                return true;
            }
        }

        return false;
    }
}
