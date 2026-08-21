<?php declare(strict_types=1);

namespace App\Publisher\HeadHtml;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class Registry
{
    /**
     * @param iterable<HeadHtmlProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(HeadHtmlProviderInterface::class)]
        private readonly iterable $providers,
    ) {}

    public function render(): string
    {
        $fragments = [];
        foreach ($this->providers as $provider) {
            $fragment = $provider->getHeadHtml();
            if ($fragment === null || trim($fragment) === '') {
                continue;
            }

            $fragments[] = trim($fragment);
        }

        return implode("\n", $fragments);
    }
}
