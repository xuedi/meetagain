<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Entity\Event;
use App\ValueObject\SimilarityScore;

/**
 * Scores how far two events of the same series have drifted apart in one locale.
 */
final readonly class EventSimilarityService
{
    private const int WEIGHT_TITLE = 20;
    private const int WEIGHT_TEASER = 20;
    private const int WEIGHT_DESCRIPTION = 60;

    /**
     * similar_text() is quadratic in the input length, and descriptions can be article-sized.
     * Comparing the first few thousand characters is enough to tell "same text" from "rewritten".
     */
    private const int DESCRIPTION_LENGTH_CAP = 3000;

    public function compare(Event $left, Event $right, string $locale): SimilarityScore
    {
        $title = $this->fieldChange($left->getTitle($locale), $right->getTitle($locale));
        $teaser = $this->fieldChange($left->getTeaser($locale), $right->getTeaser($locale));
        $description = $this->fieldChange(
            $left->getDescription($locale),
            $right->getDescription($locale),
            self::DESCRIPTION_LENGTH_CAP,
        );

        $weighted = 0.0;
        $weightSum = 0;
        foreach ([[self::WEIGHT_TITLE, $title], [self::WEIGHT_TEASER, $teaser], [self::WEIGHT_DESCRIPTION, $description]] as [$weight, $change]) {
            if ($change === null) {
                continue;
            }
            $weighted += $weight * $change;
            $weightSum += $weight;
        }

        return new SimilarityScore(
            total: $weightSum === 0 ? 0.0 : round($weighted / $weightSum, 2),
            title: $title ?? 0.0,
            teaser: $teaser ?? 0.0,
            description: $description ?? 0.0,
        );
    }

    /**
     * Percent changed for a single field, or null when the field is empty on both sides
     * and must therefore drop out of the weighting.
     */
    private function fieldChange(string $left, string $right, ?int $lengthCap = null): ?float
    {
        $normalizedLeft = $this->normalize($left, $lengthCap);
        $normalizedRight = $this->normalize($right, $lengthCap);

        if ($normalizedLeft === '' && $normalizedRight === '') {
            return null;
        }
        if ($normalizedLeft === '' || $normalizedRight === '') {
            return 100.0;
        }
        if ($normalizedLeft === $normalizedRight) {
            return 0.0;
        }

        $percentSimilar = 0.0;
        similar_text($normalizedLeft, $normalizedRight, $percentSimilar);

        return round(100.0 - $percentSimilar, 2);
    }

    private function normalize(string $value, ?int $lengthCap = null): string
    {
        $plain = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $collapsed = trim((string) preg_replace('/\s+/u', ' ', $plain));
        $lowered = mb_strtolower($collapsed);

        return $lengthCap === null ? $lowered : mb_substr($lowered, 0, $lengthCap);
    }
}
