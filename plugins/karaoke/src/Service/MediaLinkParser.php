<?php declare(strict_types=1);

namespace Plugin\Karaoke\Service;

use Plugin\Karaoke\Enum\MediaProvider;
use Plugin\Karaoke\ValueObject\MediaLink;

readonly class MediaLinkParser
{
    public function parse(string $input): ?MediaLink
    {
        $url = trim($input);
        if ($url === '') {
            return null;
        }
        if (preg_match('~^https?://~i', $url) !== 1) {
            $url = 'https://' . $url;
        }

        $match = [];
        foreach (MediaProvider::cases() as $provider) {
            foreach ($provider->getUrlPatterns() as $pattern) {
                if (preg_match($pattern, $url, $match) === 1) {
                    return new MediaLink($provider, $match['id']);
                }
            }
        }

        return null;
    }
}
