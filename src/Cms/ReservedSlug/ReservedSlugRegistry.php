<?php declare(strict_types=1);

namespace App\Cms\ReservedSlug;

use App\Repository\CmsRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Unions the reserved slugs contributed by every provider and answers whether a
 * given slug may be assigned to a CMS page.
 */
class ReservedSlugRegistry
{
    /**
     * @var array<string, true>|null
     */
    private ?array $reserved = null;

    /**
     * @param iterable<ReservedSlugProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(ReservedSlugProviderInterface::class)]
        private readonly iterable $providers,
        private readonly CmsRepository $cmsRepository,
    ) {}

    /**
     * A slug is reserved when any provider claims it, unless the page being
     * edited already owns that exact slug in the database - a page may always
     * keep its own persisted slug; it just cannot be changed to (or created
     * with) a slug another source claims.
     */
    public function isReserved(string $slug, ?int $ignoreCmsId = null): bool
    {
        $normalized = $this->normalize($slug);
        if (!isset($this->getReserved()[$normalized])) {
            return false;
        }

        if ($ignoreCmsId !== null) {
            $ownSlug = $this->cmsRepository->findSlugById($ignoreCmsId);
            if ($ownSlug !== null && $this->normalize($ownSlug) === $normalized) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string>
     */
    public function all(): array
    {
        return array_keys($this->getReserved());
    }

    /**
     * @return array<string, true>
     */
    private function getReserved(): array
    {
        if ($this->reserved !== null) {
            return $this->reserved;
        }

        $set = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getReservedSlugs() as $slug) {
                $normalized = $this->normalize($slug);
                if ($normalized === '') {
                    continue;
                }

                $set[$normalized] = true;
            }
        }

        return $this->reserved = $set;
    }

    private function normalize(string $slug): string
    {
        return mb_strtolower(trim($slug));
    }
}
