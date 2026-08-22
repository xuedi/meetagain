<?php declare(strict_types=1);

namespace App\Service\Email;

use App\Repository\CmsRepository;

readonly class EmailFooterLinkResolver
{
    public function __construct(
        private CmsRepository $cmsRepo,
    ) {}

    /**
     * @param array<int>|null $allowedCmsIds Narrows the flagged set, or null for no narrowing
     * @return list<array{label: string, url: string}>
     */
    public function resolve(string $schemeAndHost, string $locale, ?array $allowedCmsIds = null): array
    {
        $host = rtrim($schemeAndHost, '/');

        $links = [];
        foreach ($this->cmsRepo->findForEmailFooter() as $cms) {
            if ($allowedCmsIds !== null && !in_array($cms->getId(), $allowedCmsIds, true)) {
                continue;
            }

            $slug = (string) $cms->getSlug();
            $links[] = [
                'label' => $cms->getLinkName($locale) ?? $slug,
                'url' => sprintf('%s/%s/%s', $host, $locale, $slug),
            ];
        }

        return $links;
    }
}
