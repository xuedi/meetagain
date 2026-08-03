<?php declare(strict_types=1);

namespace App\Publisher\WellKnown\LlmsTxt;

final readonly class Renderer
{
    /**
     * @param list<Section> $sections
     */
    public function render(string $title, string $summary, array $sections): string
    {
        $lines = ['# ' . $title];

        $summary = $this->flatten($summary);
        if ($summary !== '') {
            $lines[] = '';
            $lines[] = '> ' . $summary;
        }

        foreach ($sections as $section) {
            if ($section->links === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '## ' . $section->heading;
            $lines[] = '';
            foreach ($section->links as $link) {
                $lines[] = $this->renderLink($link);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function renderLink(Link $link): string
    {
        $note = $this->flatten($link->note ?? '');
        if ($note === '') {
            return sprintf('- [%s](%s)', $this->flatten($link->label), $link->url);
        }

        return sprintf('- [%s](%s): %s', $this->flatten($link->label), $link->url, $note);
    }

    private function flatten(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
